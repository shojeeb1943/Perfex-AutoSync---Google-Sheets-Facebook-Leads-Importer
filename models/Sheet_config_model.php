<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sheet_config_model extends App_Model
{
    public function get_all()
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->get(db_prefix() . 'gs_lead_sync_sheets');
        $this->db->db_debug = $prev;
        return $q ? $q->result_array() : [];
    }

    public function get($id)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->where('id', $id)->get(db_prefix() . 'gs_lead_sync_sheets');
        $this->db->db_debug = $prev;
        return $q ? $q->row_array() : null;
    }

    public function get_active_sheets()
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->where('is_active', 1)->get(db_prefix() . 'gs_lead_sync_sheets');
        $this->db->db_debug = $prev;
        return $q ? $q->result_array() : [];
    }

    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->insert(db_prefix() . 'gs_lead_sync_sheets', $data);
        $id = $this->db->insert_id();
        $this->db->db_debug = $prev;
        return $id;
    }

    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', $id)->update(db_prefix() . 'gs_lead_sync_sheets', $data);
        $this->db->db_debug = $prev;
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', $id)->delete(db_prefix() . 'gs_lead_sync_sheets');
        $this->db->where('sheet_config_id', $id)->delete(db_prefix() . 'gs_lead_sync_imported');
        $this->db->db_debug = $prev;
    }

    public function mark_run($id)
    {
        $prev = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', $id)->update(db_prefix() . 'gs_lead_sync_sheets', [
            'last_run_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->db_debug = $prev;
    }
}
