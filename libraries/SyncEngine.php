<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_SyncEngine
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        // Be defensive — works whether called from controller (which loads
        // these in its constructor) or from cron / CLI.
        $this->CI->load->model('gs_lead_sync/sheet_config_model');
        $this->CI->load->model('gs_lead_sync/sync_log_model');
        $this->CI->load->model('leads_model');
    }

    public function sync_all($triggered_by = 'cron')
    {
        $sheets  = $this->CI->sheet_config_model->get_active_sheets();
        $results = [];
        foreach ($sheets as $sheet) {
            $results[$sheet['id']] = $this->sync_sheet((int)$sheet['id'], $triggered_by);
        }
        return $results;
    }

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

        $service_account_json = get_option('gs_lead_sync_service_account_json');
        if (empty($service_account_json)) {
            $stats['error_details'][] = 'Fatal: Google Service Account JSON is not configured.';
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => 'Service Account JSON not configured.']);
        }

        try {
            $sheets_client = new Gs_GoogleSheetsClient($service_account_json);
            $all_rows      = $sheets_client->get_rows($config['spreadsheet_id'], $config['sheet_tab']);
        } catch (Exception $e) {
            $stats['error_details'][] = 'Fatal: ' . $e->getMessage();
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

        $column_mapping      = json_decode($config['column_mapping']      ?? '{}', true) ?: [];
        $description_columns = json_decode($config['description_columns'] ?? '[]', true) ?: [];
        $id_column           = !empty($config['id_column']) ? $config['id_column'] : 'id';
        $skip_test_leads     = (get_option('gs_lead_sync_skip_test_leads') == '1');

        $id_col_index = array_search($id_column, $header, true);

        // Without a working ID column we can't dedupe — refuse the run instead
        // of silently re-importing every row on every cron tick.
        if ($id_col_index === false) {
            $msg = sprintf(
                'Configured unique ID column "%s" not found in sheet header. Import aborted to prevent duplicate leads.',
                $id_column
            );
            $stats['error_details'][] = 'Fatal: ' . $msg;
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => $msg]);
        }

        // Column whitelist for insert — anything outside $crm_fields + system
        // keys we set ourselves is discarded at the boundary.
        $allowed = array_merge(
            array_keys(Gs_LeadMapper::$crm_fields),
            ['source', 'status', 'addedfrom', 'assigned']
        );
        $allowed = array_flip($allowed);

        $addedfrom = function_exists('get_staff_user_id') ? (int)get_staff_user_id() : 0;

        foreach ($data_rows as $row_num => $row) {
            $row_lead_id = isset($row[$id_col_index]) ? trim((string)$row[$id_col_index]) : '';

            // Rows with no ID are un-dedupable — skip with a logged reason.
            if ($row_lead_id === '') {
                $stats['rows_skipped']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': empty ID column value, skipped.';
                continue;
            }

            if ($this->CI->sync_log_model->is_imported($sheet_config_id, $row_lead_id)) {
                $stats['rows_skipped']++;
                continue;
            }

            $lead_data = Gs_LeadMapper::map_row($header, $row, $column_mapping, $description_columns, $skip_test_leads);
            if ($lead_data === null) {
                $stats['rows_skipped']++;
                continue;
            }

            // Set source/status from the sheet config (not the sheet column).
            $lead_data['source']    = (int)$config['lead_source_id'];
            $lead_data['status']    = (int)$config['lead_status_id'];
            $lead_data['addedfrom'] = $addedfrom;

            // Trim to known columns; prevents a rogue column_mapping entry
            // from smuggling unexpected keys into the insert.
            $lead_data = array_intersect_key($lead_data, $allowed);

            $this->CI->db->trans_start();

            // leads_model::add handles hash, custom fields, hooks, activity log.
            $perfex_lead_id = $this->CI->leads_model->add($lead_data);

            if ($perfex_lead_id) {
                $this->CI->sync_log_model->mark_imported($sheet_config_id, $row_lead_id, (int)$perfex_lead_id);
            }

            $this->CI->db->trans_complete();

            if ($this->CI->db->trans_status() === false || !$perfex_lead_id) {
                $stats['rows_failed']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': leads_model::add() failed.';
                continue;
            }

            $stats['rows_imported']++;
        }

        $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
        $this->CI->sheet_config_model->mark_run($sheet_config_id);
        return $stats;
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
