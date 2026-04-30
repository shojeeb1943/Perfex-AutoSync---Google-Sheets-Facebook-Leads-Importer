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
            return array('error' => 'Sheet config not found.');
        }

        $started_at = date('Y-m-d H:i:s');
        $stats = array(
            'rows_fetched'  => 0,
            'rows_imported' => 0,
            'rows_skipped'  => 0,
            'rows_failed'   => 0,
            'error_details' => array(),
        );

        $sa_json = get_option('gs_lead_sync_service_account_json');
        if (empty($sa_json)) {
            $stats['error_details'][] = 'Service Account JSON not configured.';
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, array('error' => 'Service Account JSON not configured.'));
        }

        try {
            $client   = new Gs_GoogleSheetsClient($sa_json);
            $all_rows = $client->get_rows($config['spreadsheet_id'], $config['sheet_tab']);
        } catch (Exception $e) {
            $stats['error_details'][] = $e->getMessage();
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, array('error' => $e->getMessage()));
        }

        if (empty($all_rows) || count($all_rows) < 2) {
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return $stats;
        }

        $header    = $all_rows[0];
        $data_rows = array_slice($all_rows, 1);
        $stats['rows_fetched'] = count($data_rows);

        $cm = isset($config['column_mapping']) ? $config['column_mapping'] : '{}';
        $dc = isset($config['description_columns']) ? $config['description_columns'] : '[]';
        $sr = isset($config['skip_rows']) ? $config['skip_rows'] : '[]';
        $column_mapping      = is_array(json_decode($cm, true)) ? json_decode($cm, true) : array();
        $description_columns = is_array(json_decode($dc, true)) ? json_decode($dc, true) : array();
        $skip_row_ranges     = is_array(json_decode($sr, true)) ? json_decode($sr, true) : array();
        $id_column           = !empty($config['id_column']) ? $config['id_column'] : 'id';
        $skip_test           = (get_option('gs_lead_sync_skip_test_leads') == '1');

        $id_col_index = array_search($id_column, $header, true);
        if ($id_col_index === false) {
            $msg = 'ID column "' . $id_column . '" not found in sheet header.';
            $stats['error_details'][] = $msg;
            $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
            return array_merge($stats, array('error' => $msg));
        }

        $allowed = array_flip(array_merge(
            array_keys(Gs_LeadMapper::$crm_fields),
            array('source', 'status', 'addedfrom', 'assigned')
        ));

        $addedfrom = function_exists('get_staff_user_id') ? (int)get_staff_user_id() : 0;
        $assignee  = !empty($config['default_assignee']) ? (int)$config['default_assignee'] : 0;

        foreach ($data_rows as $row_num => $row) {
            // $row_num is 0-based within data_rows; sheet row number = $row_num + 2 (row 1 is header)
            $sheet_row_number = $row_num + 2;
            foreach ($skip_row_ranges as $range) {
                if ($sheet_row_number >= $range['from'] && $sheet_row_number <= $range['to']) {
                    $stats['rows_skipped']++;
                    continue 2;
                }
            }

            $row_lead_id = isset($row[$id_col_index]) ? trim((string)$row[$id_col_index]) : '';

            if ($row_lead_id === '') {
                $stats['rows_skipped']++;
                continue;
            }

            if ($this->CI->sync_log_model->is_imported($sheet_config_id, $row_lead_id)) {
                $stats['rows_skipped']++;
                continue;
            }

            $lead_data = Gs_LeadMapper::map_row($header, $row, $column_mapping, $description_columns, $skip_test);
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

            $perfex_lead_id = $this->_add_lead($lead_data);

            if ($perfex_lead_id) {
                $this->CI->sync_log_model->mark_imported($sheet_config_id, $row_lead_id, (int)$perfex_lead_id);
                $stats['rows_imported']++;
            } else {
                $stats['rows_failed']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': insert failed.';
            }
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
        log_message('error', 'gs_lead_sync: leads_model has no add() method');
        return false;
    }

    private function _write_log($sheet_config_id, $triggered_by, $stats, $started_at)
    {
        $this->CI->sync_log_model->log_run(array(
            'sheet_config_id' => $sheet_config_id,
            'triggered_by'    => $triggered_by,
            'rows_fetched'    => $stats['rows_fetched'],
            'rows_imported'   => $stats['rows_imported'],
            'rows_skipped'    => $stats['rows_skipped'],
            'rows_failed'     => $stats['rows_failed'],
            'error_details'   => json_encode($stats['error_details']),
            'started_at'      => $started_at,
            'finished_at'     => date('Y-m-d H:i:s'),
        ));
    }
}
