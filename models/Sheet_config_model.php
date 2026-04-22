<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sheet_config_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_all()
    {
        $q = $this->db->get(db_prefix() . 'gs_lead_sync_sheets');
        return $q ? $q->result_array() : [];
    }

    public function get($id)
    {
        $q = $this->db->where('id', $id)->get(db_prefix() . 'gs_lead_sync_sheets');
        return $q ? $q->row_array() : null;
    }

    public function get_active_sheets()
    {
        $q = $this->db->where('is_active', 1)->get(db_prefix() . 'gs_lead_sync_sheets');
        return $q ? $q->result_array() : [];
    }

    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'gs_lead_sync_sheets', $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update(db_prefix() . 'gs_lead_sync_sheets', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'gs_lead_sync_sheets');
        // Also remove imported tracking rows for this sheet
        $this->db->where('sheet_config_id', $id)->delete(db_prefix() . 'gs_lead_sync_imported');
        return $this->db->affected_rows() >= 0;
    }
}
