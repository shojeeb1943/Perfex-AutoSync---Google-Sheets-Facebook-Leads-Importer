<?php
defined('BASEPATH') or exit('No direct script access allowed');

class SyncLogModel extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function is_imported($sheet_config_id, $row_lead_id)
    {
        return $this->db
            ->where('sheet_config_id', $sheet_config_id)
            ->where('row_lead_id', $row_lead_id)
            ->count_all_results(db_prefix() . 'gs_lead_sync_imported') > 0;
    }

    public function mark_imported($sheet_config_id, $row_lead_id, $perfex_lead_id)
    {
        $this->db->insert(db_prefix() . 'gs_lead_sync_imported', [
            'sheet_config_id' => $sheet_config_id,
            'row_lead_id'     => $row_lead_id,
            'perfex_lead_id'  => $perfex_lead_id,
            'imported_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function log_run($data)
    {
        $this->db->insert(db_prefix() . 'gs_lead_sync_logs', $data);
        return $this->db->insert_id();
    }

    public function get_logs($limit = 50, $offset = 0)
    {
        $this->db->select('l.*, s.name as sheet_name');
        $this->db->from(db_prefix() . 'gs_lead_sync_logs l');
        $this->db->join(db_prefix() . 'gs_lead_sync_sheets s', 's.id = l.sheet_config_id', 'left');
        $this->db->order_by('l.id', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    public function count_logs()
    {
        return $this->db->count_all_results(db_prefix() . 'gs_lead_sync_logs');
    }

    public function clear_logs()
    {
        $this->db->truncate(db_prefix() . 'gs_lead_sync_logs');
    }
}
