<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sync_log_model extends App_Model
{
    public function is_imported($sheet_config_id, $row_lead_id)
    {
        return $this->db
            ->where('sheet_config_id', (int)$sheet_config_id)
            ->where('row_lead_id', $row_lead_id)
            ->count_all_results(db_prefix() . 'gs_lead_sync_imported') > 0;
    }

    public function mark_imported($sheet_config_id, $row_lead_id, $perfex_lead_id)
    {
        $this->db->insert(db_prefix() . 'gs_lead_sync_imported', array(
            'sheet_config_id' => (int)$sheet_config_id,
            'row_lead_id'     => $row_lead_id,
            'perfex_lead_id'  => (int)$perfex_lead_id,
            'imported_at'     => date('Y-m-d H:i:s'),
        ));
    }

    public function log_run($data)
    {
        $this->db->insert(db_prefix() . 'gs_lead_sync_logs', $data);
        return $this->db->insert_id();
    }

    public function get_logs($limit = 50, $offset = 0)
    {
        $sql = 'SELECT l.*, s.name AS sheet_name
                FROM ' . db_prefix() . 'gs_lead_sync_logs l
                LEFT JOIN ' . db_prefix() . 'gs_lead_sync_sheets s ON s.id = l.sheet_config_id
                ORDER BY l.id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $q = $this->db->query($sql);
        return $q ? $q->result_array() : array();
    }

    public function count_logs()
    {
        $q = $this->db->query('SELECT COUNT(*) AS cnt FROM ' . db_prefix() . 'gs_lead_sync_logs');
        return $q ? (int)$q->row()->cnt : 0;
    }

    public function clear_logs()
    {
        $this->db->truncate(db_prefix() . 'gs_lead_sync_logs');
    }
}
