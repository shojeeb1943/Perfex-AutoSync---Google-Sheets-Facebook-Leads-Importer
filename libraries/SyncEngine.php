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
        } catch (Throwable $e) {
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

        if ($id_col_index === false) {
            $msg = sprintf(
                'Configured unique ID column "%s" not found in sheet header. Import aborted to prevent duplicate leads.',
                $id_column
            );
            $stats['error_details'][] = 'Fatal: ' . $msg;
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, ['error' => $msg]);
        }

        $allowed = array_flip(array_merge(
            array_keys(Gs_LeadMapper::$crm_fields),
            ['source', 'status', 'addedfrom', 'assigned']
        ));

        $addedfrom = function_exists('get_staff_user_id') ? (int)get_staff_user_id() : 0;
        $assignee  = !empty($config['default_assignee']) ? (int)$config['default_assignee'] : 0;

        foreach ($data_rows as $row_num => $row) {
            $row_lead_id = isset($row[$id_col_index]) ? trim((string)$row[$id_col_index]) : '';

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

            $lead_data['source']    = (int)$config['lead_source_id'];
            $lead_data['status']    = (int)$config['lead_status_id'];
            $lead_data['addedfrom'] = $addedfrom;
            if ($assignee > 0) {
                $lead_data['assigned'] = $assignee;
            }

            $lead_data = array_intersect_key($lead_data, $allowed);

            $this->CI->db->trans_start();
            $perfex_lead_id = $this->_add_lead($lead_data);
            if ($perfex_lead_id) {
                $this->CI->sync_log_model->mark_imported($sheet_config_id, $row_lead_id, (int)$perfex_lead_id);
            }
            $this->CI->db->trans_complete();

            if ($this->CI->db->trans_status() === false || !$perfex_lead_id) {
                $stats['rows_failed']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': failed to insert lead into CRM.';
                continue;
            }

            $stats['rows_imported']++;
        }

        $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
        $this->CI->sheet_config_model->mark_run($sheet_config_id);
        return $stats;
    }

    private function _add_lead($lead_data)
    {
        $model = $this->CI->leads_model;
        if (method_exists($model, 'add')) {
            return $model->add($lead_data);
        }
        if (method_exists($model, 'add_lead')) {
            return $model->add_lead($lead_data);
        }
        throw new Exception('leads_model has no add() or add_lead() method.');
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
