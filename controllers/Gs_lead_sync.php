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

    /* ───────────────────────── Pages ───────────────────────── */

    public function index()
    {
        if (!is_admin()) { show_404(); }

        $data['sheets'] = $this->sheet_config_model->get_all();

        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = $q ? $q->result_array() : [];

        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = $q ? $q->result_array() : [];

        $data['title'] = 'Google Sheets Lead Sync';

        $sa_json = get_option('gs_lead_sync_service_account_json');
        $sa_data = $sa_json ? json_decode($sa_json, true) : null;

        $data['service_account_set']     = !empty($sa_json);
        $data['service_account_email']   = is_array($sa_data) && isset($sa_data['client_email'])  ? $sa_data['client_email'] : '';
        $data['service_account_project'] = is_array($sa_data) && isset($sa_data['project_id'])    ? $sa_data['project_id']   : '';
        $data['cron_enabled']            = get_option('gs_lead_sync_cron_enabled');
        $data['cron_interval']           = get_option('gs_lead_sync_cron_interval');
        $data['skip_test_leads']         = get_option('gs_lead_sync_skip_test_leads');
        $data['csrf_name']               = $this->security->get_csrf_token_name();
        $data['csrf_hash']               = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/settings/index', $data);
    }

    public function add_sheet()
    {
        if (!is_admin()) { show_404(); }
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';

        $data               = $this->_load_lookup_data();
        $data['sheet']      = null;
        $data['crm_fields'] = Gs_LeadMapper::$crm_fields;
        $data['title']      = 'Add Sheet Configuration';
        $data['csrf_name']  = $this->security->get_csrf_token_name();
        $data['csrf_hash']  = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function edit_sheet($id)
    {
        if (!is_admin()) { show_404(); }
        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';

        $sheet = $this->sheet_config_model->get((int) $id);
        if (!$sheet) { show_404(); }

        $sheet['column_mapping']      = json_decode(isset($sheet['column_mapping'])      ? $sheet['column_mapping']      : '{}', true);
        $sheet['description_columns'] = json_decode(isset($sheet['description_columns']) ? $sheet['description_columns'] : '[]', true);
        if (!is_array($sheet['column_mapping']))      { $sheet['column_mapping'] = []; }
        if (!is_array($sheet['description_columns'])) { $sheet['description_columns'] = []; }

        $data               = $this->_load_lookup_data();
        $data['sheet']      = $sheet;
        $data['crm_fields'] = Gs_LeadMapper::$crm_fields;
        $data['title']      = 'Edit Sheet Configuration';
        $data['csrf_name']  = $this->security->get_csrf_token_name();
        $data['csrf_hash']  = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function sync_log()
    {
        if (!is_admin()) { show_404(); }

        $page   = max(1, (int) $this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $data['logs']       = $this->sync_log_model->get_logs($limit, $offset);
        $data['total_logs'] = $this->sync_log_model->count_logs();
        $data['page']       = $page;
        $data['limit']      = $limit;
        $data['title']      = 'Sync Log';
        $data['csrf_name']  = $this->security->get_csrf_token_name();
        $data['csrf_hash']  = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/sync_log/index', $data);
    }

    /* ───────────────────────── Actions ─────────────────────── */

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

    public function save_sheet()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        $id = (int) $this->input->post('id');

        $column_mapping = $this->input->post('column_mapping');
        if (!is_array($column_mapping)) { $column_mapping = []; }
        $column_mapping = array_filter($column_mapping, function ($v) { return trim((string) $v) !== ''; });

        $spreadsheet_id = trim((string) $this->input->post('spreadsheet_id'));
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $data = [
            'name'                => trim((string) $this->input->post('name')),
            'spreadsheet_id'      => $spreadsheet_id,
            'sheet_tab'           => trim((string) $this->input->post('sheet_tab')) ?: 'Sheet1',
            'lead_status_id'      => ((int) $this->input->post('lead_status_id'))   ?: null,
            'lead_source_id'      => ((int) $this->input->post('lead_source_id'))   ?: null,
            'default_assignee'    => ((int) $this->input->post('default_assignee')) ?: null,
            'id_column'           => trim((string) $this->input->post('id_column')) ?: 'id',
            'column_mapping'      => json_encode((object) $column_mapping),
            'description_columns' => json_encode($this->input->post('description_columns') ?: []),
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
        $this->sheet_config_model->delete((int) $id);
        set_alert('success', 'Sheet configuration deleted.');
        redirect(admin_url('gs_lead_sync'));
    }

    public function clear_logs()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }
        $this->sync_log_model->clear_logs();
        set_alert('success', 'Sync log cleared.');
        redirect(admin_url('gs_lead_sync/sync_log'));
    }

    /* ───────────────────────── AJAX ────────────────────────── */

    public function detect_columns()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $spreadsheet_id = trim((string) $this->input->post('spreadsheet_id'));
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }
        $tab = trim((string) $this->input->post('sheet_tab')) ?: 'Sheet1';

        if (empty($spreadsheet_id)) {
            $this->_json(['success' => false, 'message' => 'Spreadsheet ID is required.']);
            return;
        }

        $sa_json = get_option('gs_lead_sync_service_account_json');
        if (empty($sa_json)) {
            $this->_json(['success' => false, 'message' => 'Google Service Account not configured. Go to Global Settings first.']);
            return;
        }

        try {
            $client  = new Gs_GoogleSheetsClient($sa_json);
            $headers = $client->get_headers($spreadsheet_id, $tab);

            if (empty($headers)) {
                $this->_json(['success' => false, 'message' => 'No columns found. Check the spreadsheet ID and tab name.']);
                return;
            }

            $this->_json([
                'success'   => true,
                'columns'   => $headers,
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        } catch (Exception $e) {
            $this->_json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function test_connection()
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $sa_json = trim((string) $this->input->post('service_account_json'));
        if ($sa_json === '') {
            $sa_json = (string) get_option('gs_lead_sync_service_account_json');
        }
        if ($sa_json === '') {
            $this->_json(['success' => false, 'message' => 'No Service Account JSON provided or saved.']);
            return;
        }

        try {
            $client = new Gs_GoogleSheetsClient($sa_json);
            $info   = $client->get_token_for_test();
            $this->_json([
                'success'   => true,
                'message'   => 'Google authentication succeeded.',
                'details'   => $info,
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        } catch (Exception $e) {
            $this->_json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        }
    }

    public function sync_now($id)
    {
        $this->_require_post();
        if (!is_admin()) { show_404(); }

        // Simple rate limit — 10s cooldown per sheet
        $rate_key = 'gs_sync_' . (int) $id;
        $last_hit = $this->session->userdata($rate_key);
        if ($last_hit && (time() - (int) $last_hit) < 10) {
            $this->_json(['success' => false, 'message' => 'Please wait before syncing again.']);
            return;
        }
        $this->session->set_userdata($rate_key, time());

        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

        try {
            $engine = new Gs_SyncEngine();
            $stats  = $engine->sync_sheet((int) $id, 'manual');

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
        } catch (Exception $e) {
            $this->_json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
        }
    }

    /* ───────────────────────── Helpers ─────────────────────── */

    private function _load_lookup_data()
    {
        $q = $this->db->get(db_prefix() . 'leads_status');
        $out['lead_statuses'] = $q ? $q->result_array() : [];

        $q = $this->db->get(db_prefix() . 'leads_sources');
        $out['lead_sources']  = $q ? $q->result_array() : [];

        $q = $this->db->where('active', 1)
                      ->order_by('firstname', 'asc')
                      ->get(db_prefix() . 'staff');
        $out['staff_list'] = $q ? $q->result_array() : [];

        return $out;
    }

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
