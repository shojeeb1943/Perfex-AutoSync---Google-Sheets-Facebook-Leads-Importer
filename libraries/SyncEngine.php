<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_SyncEngine
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('gs_lead_sync/sheet_config_model');
        $this->CI->load->model('gs_lead_sync/sync_log_model');
        if (!isset($this->CI->leads_model)) {
            $this->CI->load->model('leads_model');
        }
    }

    /**
     * Sync a single sheet configuration.
     *
     * @param  int    $sheet_config_id
     * @param  string $triggered_by  'manual' or 'cron'
     * @return array  Stats array with rows_fetched/imported/skipped/failed
     */
    public function sync_sheet($sheet_config_id, $triggered_by = 'manual')
    {
        $config = $this->CI->sheet_config_model->get($sheet_config_id);
        if (!$config) {
            return ['error' => 'Sheet config not found.'];
        }

        $started_at = date('Y-m-d H:i:s');
        $stats = [
            'rows_fetched'  => 0,
            'rows_imported' => 0,
            'rows_skipped'  => 0,
            'rows_failed'   => 0,
            'error_details' => [],
        ];

        $sa_json = get_option('gs_lead_sync_service_account_json');
        if (empty($sa_json)) {
            $stats['error_details'][] = 'Google Service Account JSON is not configured.';
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => 'Service Account JSON not configured.']);
        }

        // Fetch all rows from Google Sheets
        try {
            $client   = new Gs_GoogleSheetsClient($sa_json);
            $all_rows = $client->get_rows($config['spreadsheet_id'], $config['sheet_tab']);
        } catch (Exception $e) {
            $stats['error_details'][] = $e->getMessage();
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => $e->getMessage()]);
        }

        if (empty($all_rows) || count($all_rows) < 2) {
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return $stats;
        }

        $header    = $all_rows[0];
        $data_rows = array_slice($all_rows, 1);
        $stats['rows_fetched'] = count($data_rows);

        $column_mapping      = json_decode(isset($config['column_mapping'])      ? $config['column_mapping']      : '{}', true);
        $description_columns = json_decode(isset($config['description_columns']) ? $config['description_columns'] : '[]', true);
        if (!is_array($column_mapping))      { $column_mapping = []; }
        if (!is_array($description_columns)) { $description_columns = []; }

        $id_column       = !empty($config['id_column']) ? $config['id_column'] : 'id';
        $skip_test_leads = (get_option('gs_lead_sync_skip_test_leads') == '1');

        // Find the unique ID column index in the header
        $id_col_index = array_search($id_column, $header, true);
        if ($id_col_index === false) {
            $msg = 'Unique ID column "' . $id_column . '" not found in sheet header. Import aborted.';
            $stats['error_details'][] = $msg;
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => $msg]);
        }

        // Whitelist of keys that can be passed to leads_model::add()
        $allowed = array_flip(array_merge(
            array_keys(Gs_LeadMapper::$crm_fields),
            ['source', 'status', 'addedfrom', 'assigned']
        ));

        $addedfrom = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;
        $assignee  = !empty($config['default_assignee']) ? (int) $config['default_assignee'] : 0;

        foreach ($data_rows as $row_num => $row) {
            $row_lead_id = isset($row[$id_col_index]) ? trim((string) $row[$id_col_index]) : '';

            if ($row_lead_id === '') {
                $stats['rows_skipped']++;
                continue;
            }

            // Already imported?
            if ($this->CI->sync_log_model->is_imported($sheet_config_id, $row_lead_id)) {
                $stats['rows_skipped']++;
                continue;
            }

            // Map sheet row → CRM lead data
            $lead_data = Gs_LeadMapper::map_row($header, $row, $column_mapping, $description_columns, $skip_test_leads);
            if ($lead_data === null) {
                $stats['rows_skipped']++;
                continue;
            }

            // Attach assignment metadata
            $lead_data['source']    = (int) $config['lead_source_id'];
            $lead_data['status']    = (int) $config['lead_status_id'];
            $lead_data['addedfrom'] = $addedfrom;
            if ($assignee > 0) {
                $lead_data['assigned'] = $assignee;
            }

            // Whitelist filter
            $lead_data = array_intersect_key($lead_data, $allowed);

            // Insert lead via Perfex model
            $perfex_lead_id = $this->_add_lead($lead_data);

            if ($perfex_lead_id) {
                $this->CI->sync_log_model->mark_imported($sheet_config_id, $row_lead_id, (int) $perfex_lead_id);
                $stats['rows_imported']++;
            } else {
                $stats['rows_failed']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': failed to insert lead.';
            }
        }

        $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
        $this->CI->sheet_config_model->mark_run($sheet_config_id);

        return $stats;
    }

    /**
     * Insert a lead using Perfex's leads_model (fires hooks, generates hash, etc.)
     */
    private function _add_lead($lead_data)
    {
        $model = $this->CI->leads_model;
        if (method_exists($model, 'add')) {
            return $model->add($lead_data);
        }
        if (method_exists($model, 'add_lead')) {
            return $model->add_lead($lead_data);
        }
        log_message('error', 'gs_lead_sync: leads_model has no add() or add_lead() method.');
        return false;
    }

    private function _write_log($sheet_config_id, $triggered_by, $stats, $started_at)
    {
        $this->CI->sync_log_model->log_run([
            'sheet_config_id' => $sheet_config_id,
            'triggered_by'    => $triggered_by,
            'rows_fetched'    => $stats['rows_fetched'],
            'rows_imported'   => $stats['rows_imported'],
            'rows_skipped'    => $stats['rows_skipped'],
            'rows_failed'     => $stats['rows_failed'],
            'error_details'   => json_encode($stats['error_details']),
            'started_at'      => $started_at,
            'finished_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
