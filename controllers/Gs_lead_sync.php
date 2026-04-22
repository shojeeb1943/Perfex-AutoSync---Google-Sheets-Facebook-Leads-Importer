<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_lead_sync extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('gs_lead_sync/sheet_config_model');
        $this->load->model('gs_lead_sync/sync_log_model');
    }

    public function index()
    {
        if (!is_admin()) { show_404(); }

        $data['sheets']        = $this->sheet_config_model->get_all();

        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = $q ? $q->result_array() : [];
        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = $q ? $q->result_array() : [];
        $data['title']         = 'Google Sheets Lead Sync';

        $sa_json = get_option('gs_lead_sync_service_account_json');
        $sa_data = $sa_json ? json_decode($sa_json, true) : null;
        $data['service_account_set']   = !empty($sa_json);
        $data['service_account_email'] = $sa_data['client_email'] ?? '';
        $data['service_account_project'] = $sa_data['project_id'] ?? '';
        $data['cron_enabled']          = get_option('gs_lead_sync_cron_enabled');
        $data['cron_interval']         = get_option('gs_lead_sync_cron_interval');
        $data['skip_test_leads']       = get_option('gs_lead_sync_skip_test_leads');

        $this->load->view('gs_lead_sync/settings/index', $data);
    }

    public function save_settings()
    {
        if (!is_admin()) { show_404(); }

        $json_input = $this->input->post('service_account_json');
        if (!empty($json_input)) {
            $decoded = json_decode($json_input, true);
            if (!$decoded || empty($decoded['private_key']) || empty($decoded['client_email'])) {
                set_alert('danger', 'Invalid Service Account JSON — must contain private_key and client_email.');
                redirect(admin_url('gs_lead_sync'));
                return;
            }
            update_option('gs_lead_sync_service_account_json', $json_input);
        }

        update_option('gs_lead_sync_cron_enabled',    $this->input->post('cron_enabled')    ? '1' : '0');
        update_option('gs_lead_sync_cron_interval',   $this->input->post('cron_interval'));
        update_option('gs_lead_sync_skip_test_leads', $this->input->post('skip_test_leads') ? '1' : '0');

        set_alert('success', 'Settings saved successfully.');
        redirect(admin_url('gs_lead_sync'));
    }

    public function add_sheet()
    {
        if (!is_admin()) { show_404(); }
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';

        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = $q ? $q->result_array() : [];
        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = $q ? $q->result_array() : [];
        $data['sheet']         = null;
        $data['crm_fields']    = LeadMapper::$crm_fields;
        $data['title']         = 'Add Sheet Configuration';

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function edit_sheet($id)
    {
        if (!is_admin()) { show_404(); }
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';

        $sheet = $this->sheet_config_model->get($id);
        if (!$sheet) { show_404(); }

        $sheet['column_mapping']      = json_decode($sheet['column_mapping']      ?? '{}', true) ?: [];
        $sheet['description_columns'] = json_decode($sheet['description_columns'] ?? '[]', true) ?: [];

        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = $q ? $q->result_array() : [];
        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = $q ? $q->result_array() : [];
        $data['sheet']         = $sheet;
        $data['crm_fields']    = LeadMapper::$crm_fields;
        $data['title']         = 'Edit Sheet Configuration';

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function save_sheet()
    {
        if (!is_admin()) { show_404(); }

        $id = (int)$this->input->post('id');

        $column_mapping = $this->input->post('column_mapping') ?: [];
        $column_mapping = array_filter($column_mapping, function ($v) { return $v !== ''; });

        $description_columns = $this->input->post('description_columns') ?: [];

        $spreadsheet_id = trim($this->input->post('spreadsheet_id'));
        // Extract ID from full Google Sheets URL if pasted
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $data = [
            'name'                => trim($this->input->post('name')),
            'spreadsheet_id'      => $spreadsheet_id,
            'sheet_tab'           => trim($this->input->post('sheet_tab')) ?: 'Sheet1',
            'lead_status_id'      => (int)$this->input->post('lead_status_id'),
            'lead_source_id'      => (int)$this->input->post('lead_source_id'),
            'id_column'           => trim($this->input->post('id_column')) ?: 'id',
            'column_mapping'      => json_encode($column_mapping),
            'description_columns' => json_encode(array_values($description_columns)),
            'is_active'           => $this->input->post('is_active') ? 1 : 0,
        ];

        if (empty($data['name']) || empty($data['spreadsheet_id'])) {
            set_alert('danger', 'Name and Spreadsheet ID are required.');
            redirect($id ? admin_url('gs_lead_sync/edit_sheet/' . $id) : admin_url('gs_lead_sync/add_sheet'));
            return;
        }

        if ($id) {
            $this->sheet_config_model->update($id, $data);
            set_alert('success', 'Sheet configuration updated.');
        } else {
            $this->sheet_config_model->insert($data);
            set_alert('success', 'Sheet configuration added.');
        }

        redirect(admin_url('gs_lead_sync'));
    }

    public function delete_sheet($id)
    {
        if (!is_admin()) { show_404(); }
        $this->sheet_config_model->delete($id);
        set_alert('success', 'Sheet configuration deleted.');
        redirect(admin_url('gs_lead_sync'));
    }

    // AJAX: POST admin/gs_lead_sync/detect_columns
    public function detect_columns()
    {
        if (!is_admin()) { show_404(); }
        header('Content-Type: application/json');
        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $spreadsheet_id = trim($this->input->post('spreadsheet_id'));
        $tab_name       = trim($this->input->post('sheet_tab')) ?: 'Sheet1';

        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $service_account_json = get_option('gs_lead_sync_service_account_json');
        if (empty($service_account_json)) {
            echo json_encode(['success' => false, 'message' => 'Service Account JSON is not configured in Global Settings.']);
            return;
        }

        try {
            $client  = new GoogleSheetsClient($service_account_json);
            $headers = $client->get_headers($spreadsheet_id, $tab_name);
            echo json_encode(['success' => true, 'columns' => $headers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // AJAX: POST admin/gs_lead_sync/sync_now/{id}
    public function sync_now($id)
    {
        if (!is_admin()) { show_404(); }
        header('Content-Type: application/json');
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

        $engine = new SyncEngine();
        $stats  = $engine->sync_sheet((int)$id, 'manual');

        if (isset($stats['error'])) {
            echo json_encode(['success' => false, 'message' => $stats['error'], 'stats' => $stats]);
        } else {
            echo json_encode(['success' => true, 'stats' => $stats]);
        }
    }

    public function sync_log()
    {
        if (!is_admin()) { show_404(); }

        $page   = max(1, (int)$this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $data['logs']       = $this->sync_log_model->get_logs($limit, $offset);
        $data['total_logs'] = $this->sync_log_model->count_logs();
        $data['page']       = $page;
        $data['limit']      = $limit;
        $data['title']      = 'Sync Log';

        $this->load->view('gs_lead_sync/sync_log/index', $data);
    }

    public function clear_logs()
    {
        if (!is_admin()) { show_404(); }
        $this->sync_log_model->clear_logs();
        set_alert('success', 'Sync log cleared.');
        redirect(admin_url('gs_lead_sync/sync_log'));
    }
}
