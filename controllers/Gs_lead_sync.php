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

        $data['sheets'] = $this->sheet_config_model->get_all();

        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = $q ? $q->result_array() : [];
        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = $q ? $q->result_array() : [];
        $data['title']         = 'Google Sheets Lead Sync';

        $sa_json = get_option('gs_lead_sync_service_account_json');
        $sa_data = $sa_json ? json_decode($sa_json, true) : null;
        $data['service_account_set']     = !empty($sa_json);
        $data['service_account_email']   = $sa_data['client_email'] ?? '';
        $data['service_account_project'] = $sa_data['project_id']   ?? '';
        $data['cron_enabled']            = get_option('gs_lead_sync_cron_enabled');
        $data['cron_interval']           = get_option('gs_lead_sync_cron_interval');
        $data['skip_test_leads']         = get_option('gs_lead_sync_skip_test_leads');

        $this->load->view('gs_lead_sync/settings/index', $data);
    }

    public function save_settings()
    {
        $this->_require_post();
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

        $data                  = $this->_load_lookup_data();
        $data['sheet']         = null;
        $data['crm_fields']    = Gs_LeadMapper::$crm_fields;
        $data['title']         = 'Add Sheet Configuration';

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function edit_sheet($id)
    {
        if (!is_admin()) { show_404(); }
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';

        $sheet = $this->sheet_config_model->get((int)$id);
        if (!$sheet) { show_404(); }

        $sheet['column_mapping']      = json_decode($sheet['column_mapping']      ?? '{}', true) ?: [];
        $sheet['description_columns'] = json_decode($sheet['description_columns'] ?? '[]', true) ?: [];

        $data                  = $this->_load_lookup_data();
        $data['sheet']         = $sheet;
        $data['crm_fields']    = Gs_LeadMapper::$crm_fields;
        $data['title']         = 'Edit Sheet Configuration';

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    private function _load_lookup_data()
    {
        $q = $this->db->get(db_prefix() . 'leads_status');
        $out['lead_statuses'] = $q ? $q->result_array() : [];

        $q = $this->db->get(db_prefix() . 'leads_sources');
        $out['lead_sources']  = $q ? $q->result_array() : [];

        // Active staff for the Default Assignee dropdown.
        $q = $this->db->where('active', 1)
                      ->order_by('firstname', 'asc')
                      ->get(db_prefix() . 'staff');
        $out['staff_list']    = $q ? $q->result_array() : [];

        return $out;
    }

    public function save_sheet()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        $id = (int)$this->input->post('id');

        $column_mapping = $this->input->post('column_mapping') ?: [];
        $column_mapping = array_filter($column_mapping, function ($v) { return $v !== ''; });

        $description_columns = $this->input->post('description_columns') ?: [];

        $spreadsheet_id = trim($this->input->post('spreadsheet_id'));
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $lead_status_id   = $this->input->post('lead_status_id');
        $lead_source_id   = $this->input->post('lead_source_id');
        $default_assignee = $this->input->post('default_assignee');

        $data = [
            'name'                => trim($this->input->post('name')),
            'spreadsheet_id'      => $spreadsheet_id,
            'sheet_tab'           => trim($this->input->post('sheet_tab')) ?: 'Sheet1',
            'lead_status_id'      => ($lead_status_id === '' || $lead_status_id === null) ? null : (int)$lead_status_id,
            'lead_source_id'      => ($lead_source_id === '' || $lead_source_id === null) ? null : (int)$lead_source_id,
            'default_assignee'    => ($default_assignee === '' || $default_assignee === null) ? null : (int)$default_assignee,
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
        $this->_require_post();
        if (!is_admin()) { show_404(); }
        $this->sheet_config_model->delete((int)$id);
        set_alert('success', 'Sheet configuration deleted.');
        redirect(admin_url('gs_lead_sync'));
    }

    // AJAX: POST admin/gs_lead_sync/test_connection
    // Verifies that the saved Service Account JSON can authenticate against
    // Google's OAuth endpoint. Doesn't touch any spreadsheet.
    public function test_connection()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        // Allow testing freshly-pasted JSON before the user clicks Save, by
        // accepting the payload from POST and falling back to the saved option.
        $service_account_json = trim((string)$this->input->post('service_account_json'));
        if ($service_account_json === '') {
            $service_account_json = (string)get_option('gs_lead_sync_service_account_json');
        }

        if ($service_account_json === '') {
            $this->_json([
                'success'   => false,
                'message'   => 'No Service Account JSON provided or saved. Paste your JSON in the textarea or save it first.',
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
            return;
        }

        try {
            $client = new Gs_GoogleSheetsClient($service_account_json);
            // get_token_for_test() walks the full JWT/OAuth round-trip so we
            // catch every credential, network, or SSL failure mode.
            $info = $client->get_token_for_test();

            $this->_json([
                'success'   => true,
                'message'   => 'Google authentication succeeded. Service account is valid and reachable.',
                'details'   => $info,
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        } catch (Throwable $e) {
            $this->_json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        }
    }

    // AJAX: POST admin/gs_lead_sync/detect_columns
    public function detect_columns()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $spreadsheet_id = trim($this->input->post('spreadsheet_id'));
        $tab_name       = trim($this->input->post('sheet_tab')) ?: 'Sheet1';

        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $service_account_json = get_option('gs_lead_sync_service_account_json');
        if (empty($service_account_json)) {
            $this->_json([
                'success'   => false,
                'message'   => 'Service Account JSON is not configured in Global Settings.',
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
            return;
        }

        try {
            $client  = new Gs_GoogleSheetsClient($service_account_json);
            $headers = $client->get_headers($spreadsheet_id, $tab_name);
            $this->_json([
                'success'   => true,
                'columns'   => $headers,
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        } catch (Throwable $e) {
            $this->_json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        }
    }

    // AJAX: POST admin/gs_lead_sync/sync_now/{id}
    public function sync_now($id)
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

        try {
            $engine = new Gs_SyncEngine();
            $stats  = $engine->sync_sheet((int)$id, 'manual');

            if (isset($stats['error'])) {
                $this->_json([
                    'success'   => false,
                    'message'   => $stats['error'],
                    'stats'     => $stats,
                    'csrf_hash' => $this->security->get_csrf_hash(),
                ]);
                return;
            }
            $this->_json([
                'success'   => true,
                'stats'     => $stats,
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        } catch (Throwable $e) {
            $this->_json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
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
        $this->_require_post();
        if (!is_admin()) { show_404(); }
        $this->sync_log_model->clear_logs();
        set_alert('success', 'Sync log cleared.');
        redirect(admin_url('gs_lead_sync/sync_log'));
    }

    // -------------------------------------------------------------------------

    /**
     * Flush any buffered output (hooks, notices) and emit a pure JSON body.
     * Prior versions relied on set_output() alone, which doesn't stop stray
     * echoes from other app_admin_head hooks leaking into AJAX responses.
     */
    private function _json($payload)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload);
        exit;
    }

    private function _require_post()
    {
        if (strtolower($this->input->method()) !== 'post') {
            show_404();
        }
    }
}
