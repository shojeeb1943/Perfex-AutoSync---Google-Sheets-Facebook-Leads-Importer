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
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->get($this->_table);
        $this->db->db_debug = $d;
        return ($q && $q->num_rows() >= 0) ? $q->result_array() : array();
    }

    public function get($id)
    {
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->where('id', (int)$id)->get($this->_table);
        $this->db->db_debug = $d;
        return ($q && $q->num_rows() > 0) ? $q->row_array() : null;
    }

    public function get_active_sheets()
    {
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->where('is_active', 1)->get($this->_table);
        $this->db->db_debug = $d;
        return ($q && $q->num_rows() >= 0) ? $q->result_array() : array();
    }

    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $ok = $this->db->insert($this->_table, $data);
        $err = $this->db->error();
        $id  = $this->db->insert_id();
        $this->db->db_debug = $d;

        if (!$ok || (isset($err['code']) && $err['code'] != 0)) {
            log_message('error', 'gs_lead_sync insert error: ' . json_encode($err));
            return false;
        }
        return $id;
    }

    public function update($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', (int)$id)->update($this->_table, $data);
        $err = $this->db->error();
        $this->db->db_debug = $d;

        if (isset($err['code']) && $err['code'] != 0) {
            log_message('error', 'gs_lead_sync update error: ' . json_encode($err));
            return false;
        }
        return true;
    }

    public function delete($id)
    {
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', (int)$id)->delete($this->_table);
        $this->db->where('sheet_config_id', (int)$id)->delete(db_prefix() . 'gs_lead_sync_imported');
        $this->db->db_debug = $d;
    }

    public function mark_run($id)
    {
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->where('id', (int)$id)->update($this->_table, array(
            'last_run_at' => date('Y-m-d H:i:s'),
        ));
        $this->db->db_debug = $d;
    }
}
