<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sheet_config_model extends App_Model
{
    private $_table;

    public function __construct()
    {
        parent::__construct();
        $this->_table = db_prefix() . 'gs_lead_sync_sheets';
    }

    public function get_all()
    {
        $q = $this->db->get($this->_table);
        return $q ? $q->result_array() : [];
    }

    public function get($id)
    {
        $q = $this->db->where('id', (int) $id)->get($this->_table);
        return $q ? $q->row_array() : null;
    }

    public function get_active_sheets()
    {
        $q = $this->db->where('is_active', 1)->get($this->_table);
        return $q ? $q->result_array() : [];
    }

    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->_table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update($this->_table, $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id)->delete($this->_table);
        $this->db->where('sheet_config_id', (int) $id)->delete(db_prefix() . 'gs_lead_sync_imported');
    }

    public function mark_run($id)
    {
        $this->db->where('id', (int) $id)->update($this->_table, [
            'last_run_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
