<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__FILE__) . '/LeadMapper.php';
require_once dirname(__FILE__) . '/GoogleSheetsClient.php';

class SyncEngine
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('gs_lead_sync/SheetConfigModel', 'sheet_config_model');
        $this->CI->load->model('gs_lead_sync/SyncLogModel', 'sync_log_model');
    }

    public function sync_all($triggered_by = 'cron')
    {
        $sheets  = $this->CI->sheet_config_model->get_active_sheets();
        $results = [];
        foreach ($sheets as $sheet) {
            $results[$sheet['id']] = $this->sync_sheet($sheet['id'], $triggered_by);
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
            $sheets_client = new GoogleSheetsClient($service_account_json);
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

        $id_col_index = array_search($id_column, $header);

        foreach ($data_rows as $row_num => $row) {
            $row_lead_id = '';
            if ($id_col_index !== false && isset($row[$id_col_index])) {
                $row_lead_id = trim($row[$id_col_index]);
            }

            // Skip already-imported rows
            if ($row_lead_id !== '' && $this->CI->sync_log_model->is_imported($sheet_config_id, $row_lead_id)) {
                $stats['rows_skipped']++;
                continue;
            }

            // Map row to lead data; returns null if test lead or missing name
            $lead_data = LeadMapper::map_row($header, $row, $column_mapping, $description_columns, $skip_test_leads);

            if ($lead_data === null) {
                $stats['rows_skipped']++;
                continue;
            }

            // Set source and status from sheet config (not from the sheet column)
            $lead_data['source']    = (int)$config['lead_source_id'];
            $lead_data['status']    = (int)$config['lead_status_id'];
            $lead_data['dateadded'] = date('Y-m-d H:i:s');
            $lead_data['addedfrom'] = 0;

            // XSS-clean only string fields
            foreach ($lead_data as $key => $val) {
                if (is_string($val)) {
                    $lead_data[$key] = $this->CI->security->xss_clean($val);
                }
            }

            $inserted = $this->CI->db->insert(db_prefix() . 'leads', $lead_data);

            if ($inserted) {
                $perfex_lead_id = $this->CI->db->insert_id();
                if ($perfex_lead_id && $row_lead_id !== '') {
                    $this->CI->sync_log_model->mark_imported($sheet_config_id, $row_lead_id, $perfex_lead_id);
                }
                $stats['rows_imported']++;
            } else {
                $stats['rows_failed']++;
                $stats['error_details'][] = 'Row ' . ($row_num + 2) . ': DB insert failed — ' . $this->CI->db->error()['message'];
            }
        }

        $this->_write_log($sheet_config_id, $triggered_by, $stats, $started_at);
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
