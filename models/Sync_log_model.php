<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sync_log_model extends App_Model
{
    public function is_imported($sheet_config_id, $row_lead_id)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $count = $this->db
            ->where('sheet_config_id', (int)$sheet_config_id)
            ->where('row_lead_id', $row_lead_id)
            ->count_all_results(db_prefix() . 'gs_lead_sync_imported');
        $this->db->db_debug = $prev;
        return $count > 0;
    }

    public function mark_imported($sheet_config_id, $row_lead_id, $perfex_lead_id)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->insert(db_prefix() . 'gs_lead_sync_imported', array(
            'sheet_config_id' => (int)$sheet_config_id,
            'row_lead_id'     => $row_lead_id,
            'perfex_lead_id'  => (int)$perfex_lead_id,
            'imported_at'     => date('Y-m-d H:i:s'),
        ));
        $this->db->db_debug = $prev;
    }

    public function log_run($data)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->insert(db_prefix() . 'gs_lead_sync_logs', $data);
        $id = $this->db->insert_id();
        $this->db->db_debug = $prev;
        return $id;
    }

    public function get_logs($limit = 50, $offset = 0)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->query(
            'SELECT l.*, s.name AS sheet_name
             FROM ' . db_prefix() . 'gs_lead_sync_logs l
             LEFT JOIN ' . db_prefix() . 'gs_lead_sync_sheets s ON s.id = l.sheet_config_id
             ORDER BY l.id DESC
             LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        );
        $this->db->db_debug = $prev;
        if (!$q) { return array(); }
        return $q->result_array();
    }

    public function count_logs()
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->query('SELECT COUNT(*) AS cnt FROM ' . db_prefix() . 'gs_lead_sync_logs');
        $this->db->db_debug = $prev;
        if (!$q) { return 0; }
        return (int)$q->row()->cnt;
    }

    public function clear_logs()
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->truncate(db_prefix() . 'gs_lead_sync_logs');
        $this->db->db_debug = $prev;
    }
}
