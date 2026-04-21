<?php
defined('BASEPATH') or exit('No direct script access allowed');

class SheetConfigModel extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_all()
    {
        return $this->db->get(db_prefix() . 'gs_lead_sync_sheets')->result_array();
    }

    public function get($id)
    {
        return $this->db->where('id', $id)->get(db_prefix() . 'gs_lead_sync_sheets')->row_array();
    }

    public function get_active_sheets()
    {
        return $this->db->where('is_active', 1)->get(db_prefix() . 'gs_lead_sync_sheets')->result_array();
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
