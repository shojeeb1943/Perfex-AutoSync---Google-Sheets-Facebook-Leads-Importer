<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_lead_sync extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->_ensure_tables();
        $this->load->model('gs_lead_sync/sheet_config_model');
        $this->load->model('gs_lead_sync/sync_log_model');
    }

    /* ──── Diagnostic ──── */

    public function diag()
    {
        if (!is_admin()) { show_404(); }

        header('Content-Type: text/plain; charset=UTF-8');
        $p = db_prefix();

        echo "=== GS Lead Sync Diagnostic ===\n\n";
        echo "PHP version:    " . PHP_VERSION . "\n";
        echo "CI version:     " . CI_VERSION . "\n";
        echo "DB prefix:      " . $p . "\n";
        echo "DB charset:     " . $this->db->char_set . "\n";
        echo "Module dir:     " . GS_LEAD_SYNC_DIR . "\n\n";

        echo "--- Tables ---\n";
        echo "sheets table:   " . ($this->db->table_exists($p . 'gs_lead_sync_sheets')   ? 'EXISTS' : 'MISSING') . "\n";
        echo "imported table: " . ($this->db->table_exists($p . 'gs_lead_sync_imported') ? 'EXISTS' : 'MISSING') . "\n";
        echo "logs table:     " . ($this->db->table_exists($p . 'gs_lead_sync_logs')     ? 'EXISTS' : 'MISSING') . "\n\n";

        // Show actual column definitions
        if ($this->db->table_exists($p . 'gs_lead_sync_sheets')) {
            echo "--- Sheets Table Columns ---\n";
            $d = $this->db->db_debug;
            $this->db->db_debug = false;
            $cols = $this->db->query("SHOW COLUMNS FROM `{$p}gs_lead_sync_sheets`");
            $this->db->db_debug = $d;
            if ($cols) {
                foreach ($cols->result_array() as $c) {
                    echo "  " . $c['Field'] . ": " . $c['Type'] . " " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . " default=" . var_export($c['Default'], true) . "\n";
                }
            }
            echo "\n";
        }

        echo "--- Options ---\n";
        echo "cron_enabled:    " . var_export(get_option('gs_lead_sync_cron_enabled'), true) . "\n";
        echo "cron_interval:   " . var_export(get_option('gs_lead_sync_cron_interval'), true) . "\n";
        echo "skip_test_leads: " . var_export(get_option('gs_lead_sync_skip_test_leads'), true) . "\n";
        echo "sa_json set:     " . (get_option('gs_lead_sync_service_account_json') ? 'YES' : 'NO') . "\n\n";

        echo "--- Files ---\n";
        $files = array(
            'controllers/Gs_lead_sync.php',
            'models/Sheet_config_model.php',
            'models/Sync_log_model.php',
            'libraries/SyncEngine.php',
            'libraries/GoogleSheetsClient.php',
            'libraries/LeadMapper.php',
            'views/settings/index.php',
            'views/settings/sheet_form.php',
            'views/sync_log/index.php',
        );
        foreach ($files as $f) {
            $path = GS_LEAD_SYNC_DIR . $f;
            echo $f . ': ' . (file_exists($path) ? 'OK (' . filesize($path) . ' bytes)' : 'MISSING!') . "\n";
        }

        echo "\n--- Test DB Insert ---\n";
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $test_data = array(
            'name'                => '__diag_test__',
            'spreadsheet_id'      => 'test_id',
            'sheet_tab'           => 'Sheet1',
            'lead_status_id'      => 0,
            'lead_source_id'      => 0,
            'default_assignee'    => 0,
            'column_mapping'      => '{}',
            'description_columns' => '[]',
            'id_column'           => 'id',
            'is_active'           => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        );
        $ok = $this->db->insert($p . 'gs_lead_sync_sheets', $test_data);
        $err = $this->db->error();
        $test_id = $this->db->insert_id();
        if ($ok && (!isset($err['code']) || $err['code'] == 0)) {
            echo "INSERT: OK (id={$test_id})\n";
            $this->db->where('id', $test_id)->delete($p . 'gs_lead_sync_sheets');
            echo "DELETE: OK (cleaned up)\n";
        } else {
            echo "INSERT FAILED!\n";
            echo "Error code: " . (isset($err['code']) ? $err['code'] : 'none') . "\n";
            echo "Error msg:  " . (isset($err['message']) ? $err['message'] : 'none') . "\n";
        }
        $this->db->db_debug = $d;

        echo "\n--- PHP Extensions ---\n";
        echo "curl:    " . (extension_loaded('curl')    ? 'YES' : 'NO') . "\n";
        echo "openssl: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";
        echo "json:    " . (extension_loaded('json')    ? 'YES' : 'NO') . "\n";

        exit;
    }

    /* ──── Main page ──── */

    public function index()
    {
        if (!is_admin()) { show_404(); }

        $data = array();
        $data['sheets'] = $this->sheet_config_model->get_all();

        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $q = $this->db->get(db_prefix() . 'leads_status');
        $data['lead_statuses'] = ($q && $q->num_rows() >= 0) ? $q->result_array() : array();
        $q = $this->db->get(db_prefix() . 'leads_sources');
        $data['lead_sources']  = ($q && $q->num_rows() >= 0) ? $q->result_array() : array();
        $this->db->db_debug = $d;

        $data['title'] = 'Google Sheets Lead Sync';

        $sa_json = get_option('gs_lead_sync_service_account_json');
        $sa_data = $sa_json ? json_decode($sa_json, true) : null;

        $data['service_account_set']     = !empty($sa_json);
        $data['service_account_email']   = (is_array($sa_data) && isset($sa_data['client_email'])) ? $sa_data['client_email'] : '';
        $data['service_account_project'] = (is_array($sa_data) && isset($sa_data['project_id']))   ? $sa_data['project_id']   : '';
        $data['cron_enabled']            = get_option('gs_lead_sync_cron_enabled');
        $data['cron_interval']           = get_option('gs_lead_sync_cron_interval');
        $data['skip_test_leads']         = get_option('gs_lead_sync_skip_test_leads');
        $data['csrf_name']               = $this->security->get_csrf_token_name();
        $data['csrf_hash']               = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/settings/index', $data);
    }

    /* ──── Sheet CRUD ──── */

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

        $sheet = $this->sheet_config_model->get((int)$id);
        if (!$sheet) { show_404(); }

        $cm = isset($sheet['column_mapping']) ? $sheet['column_mapping'] : '{}';
        $dc = isset($sheet['description_columns']) ? $sheet['description_columns'] : '[]';
        $sheet['column_mapping']      = is_array(json_decode($cm, true)) ? json_decode($cm, true) : array();
        $sheet['description_columns'] = is_array(json_decode($dc, true)) ? json_decode($dc, true) : array();

        $data               = $this->_load_lookup_data();
        $data['sheet']      = $sheet;
        $data['crm_fields'] = Gs_LeadMapper::$crm_fields;
        $data['title']      = 'Edit Sheet Configuration';
        $data['csrf_name']  = $this->security->get_csrf_token_name();
        $data['csrf_hash']  = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/settings/sheet_form', $data);
    }

    public function save_sheet()
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }

        $id = (int)$this->input->post('id');

        $column_mapping = $this->input->post('column_mapping');
        if (!is_array($column_mapping)) { $column_mapping = array(); }
        $filtered = array();
        foreach ($column_mapping as $k => $v) {
            $val = trim((string)$v);
            if ($val !== '') { $filtered[$k] = $val; }
        }

        $spreadsheet_id = trim((string)$this->input->post('spreadsheet_id'));
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }

        $desc_cols = $this->input->post('description_columns');
        if (!is_array($desc_cols)) { $desc_cols = array(); }

        $sheet_tab = trim((string)$this->input->post('sheet_tab'));
        if ($sheet_tab === '') { $sheet_tab = 'Sheet1'; }

        $id_column = trim((string)$this->input->post('id_column'));
        if ($id_column === '') { $id_column = 'id'; }

        $data = array(
            'name'                => trim((string)$this->input->post('name')),
            'spreadsheet_id'      => $spreadsheet_id,
            'sheet_tab'           => $sheet_tab,
            'lead_status_id'      => max(0, (int)$this->input->post('lead_status_id')),
            'lead_source_id'      => max(0, (int)$this->input->post('lead_source_id')),
            'default_assignee'    => max(0, (int)$this->input->post('default_assignee')),
            'id_column'           => $id_column,
            'column_mapping'      => json_encode(count($filtered) > 0 ? (object)$filtered : new stdClass()),
            'description_columns' => json_encode(array_values($desc_cols)),
            'is_active'           => $this->input->post('is_active') ? 1 : 0,
        );

        if ($data['name'] === '' || $data['spreadsheet_id'] === '') {
            set_alert('danger', 'Name and Spreadsheet ID are required.');
            redirect($id ? admin_url('gs_lead_sync/edit_sheet/' . $id) : admin_url('gs_lead_sync/add_sheet'));
            return;
        }

        if ($id > 0) {
            $result = $this->sheet_config_model->update($id, $data);
        } else {
            $result = $this->sheet_config_model->insert($data);
        }

        if ($result === false) {
            set_alert('danger', 'Database error saving sheet. Check application/logs/ for details.');
        } else {
            set_alert('success', $id > 0 ? 'Sheet updated.' : 'Sheet added.');
        }
        redirect(admin_url('gs_lead_sync'));
    }

    public function delete_sheet($id)
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }
        $this->sheet_config_model->delete((int)$id);
        set_alert('success', 'Sheet deleted.');
        redirect(admin_url('gs_lead_sync'));
    }

    /* ──── Settings ──── */

    public function save_settings()
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }

        $json_input = $this->input->post('service_account_json');
        if (!empty($json_input)) {
            $decoded = json_decode($json_input, true);
            if (!$decoded || empty($decoded['private_key']) || empty($decoded['client_email'])) {
                set_alert('danger', 'Invalid Service Account JSON.');
                redirect(admin_url('gs_lead_sync'));
                return;
            }
            update_option('gs_lead_sync_service_account_json', $json_input);
        }

        update_option('gs_lead_sync_cron_enabled',    $this->input->post('cron_enabled')    ? '1' : '0');
        update_option('gs_lead_sync_cron_interval',   (string)$this->input->post('cron_interval'));
        update_option('gs_lead_sync_skip_test_leads', $this->input->post('skip_test_leads') ? '1' : '0');

        set_alert('success', 'Settings saved.');
        redirect(admin_url('gs_lead_sync'));
    }

    /* ──── Sync Log ──── */

    public function sync_log()
    {
        if (!is_admin()) { show_404(); }

        $page   = max(1, (int)$this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $data = array();
        $data['logs']       = $this->sync_log_model->get_logs($limit, $offset);
        $data['total_logs'] = $this->sync_log_model->count_logs();
        $data['page']       = $page;
        $data['limit']      = $limit;
        $data['title']      = 'Sync Log';
        $data['csrf_name']  = $this->security->get_csrf_token_name();
        $data['csrf_hash']  = $this->security->get_csrf_hash();

        $this->load->view('gs_lead_sync/sync_log/index', $data);
    }

    public function clear_logs()
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }
        $this->sync_log_model->clear_logs();
        set_alert('success', 'Sync log cleared.');
        redirect(admin_url('gs_lead_sync/sync_log'));
    }

    /* ──── AJAX ──── */

    public function detect_columns()
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $spreadsheet_id = trim((string)$this->input->post('spreadsheet_id'));
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_\-]+)/', $spreadsheet_id, $m)) {
            $spreadsheet_id = $m[1];
        }
        $tab = trim((string)$this->input->post('sheet_tab'));
        if ($tab === '') { $tab = 'Sheet1'; }

        if ($spreadsheet_id === '') {
            return $this->_json(array('success' => false, 'message' => 'Spreadsheet ID required.'));
        }

        $sa_json = get_option('gs_lead_sync_service_account_json');
        if (empty($sa_json)) {
            return $this->_json(array('success' => false, 'message' => 'Service Account not configured.'));
        }

        try {
            $client  = new Gs_GoogleSheetsClient($sa_json);
            $headers = $client->get_headers($spreadsheet_id, $tab);
            if (empty($headers)) {
                return $this->_json(array('success' => false, 'message' => 'No columns found.'));
            }
            $this->_json(array('success' => true, 'columns' => $headers, 'csrf_hash' => $this->security->get_csrf_hash()));
        } catch (Exception $e) {
            $this->_json(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function test_connection()
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';

        $sa_json = trim((string)$this->input->post('service_account_json'));
        if ($sa_json === '') {
            $sa_json = (string)get_option('gs_lead_sync_service_account_json');
        }
        if ($sa_json === '') {
            return $this->_json(array('success' => false, 'message' => 'No Service Account JSON.'));
        }

        try {
            $client = new Gs_GoogleSheetsClient($sa_json);
            $info   = $client->get_token_for_test();
            $this->_json(array('success' => true, 'message' => 'Auth OK.', 'details' => $info, 'csrf_hash' => $this->security->get_csrf_hash()));
        } catch (Exception $e) {
            $this->_json(array('success' => false, 'message' => $e->getMessage(), 'csrf_hash' => $this->security->get_csrf_hash()));
        }
    }

    public function sync_now($id)
    {
        if (strtolower($this->input->method()) !== 'post') { show_404(); }
        if (!is_admin()) { show_404(); }

        require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
        require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

        try {
            $engine = new Gs_SyncEngine();
            $stats  = $engine->sync_sheet((int)$id, 'manual');

            if (isset($stats['error'])) {
                return $this->_json(array('success' => false, 'message' => $stats['error'], 'stats' => $stats, 'csrf_hash' => $this->security->get_csrf_hash()));
            }
            $this->_json(array('success' => true, 'stats' => $stats, 'csrf_hash' => $this->security->get_csrf_hash()));
        } catch (Exception $e) {
            $this->_json(array('success' => false, 'message' => $e->getMessage(), 'csrf_hash' => $this->security->get_csrf_hash()));
        }
    }

    /* ──── Private helpers ──── */

    private function _load_lookup_data()
    {
        $out = array();
        $d = $this->db->db_debug;
        $this->db->db_debug = false;

        $q = $this->db->get(db_prefix() . 'leads_status');
        $out['lead_statuses'] = ($q && $q->num_rows() >= 0) ? $q->result_array() : array();

        $q = $this->db->get(db_prefix() . 'leads_sources');
        $out['lead_sources']  = ($q && $q->num_rows() >= 0) ? $q->result_array() : array();

        $q = $this->db->where('active', 1)->order_by('firstname', 'asc')->get(db_prefix() . 'staff');
        $out['staff_list'] = ($q && $q->num_rows() >= 0) ? $q->result_array() : array();

        $this->db->db_debug = $d;
        return $out;
    }

    private function _json($payload)
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload);
        exit;
    }

    private function _ensure_tables()
    {
        $d = $this->db->db_debug;
        $this->db->db_debug = false;
        $p = db_prefix();
        $t = $p . 'gs_lead_sync_sheets';

        if (!$this->db->table_exists($t)) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$t}` (
                    `id`                  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name`                VARCHAR(255) NOT NULL DEFAULT '',
                    `spreadsheet_id`      VARCHAR(255) NOT NULL DEFAULT '',
                    `sheet_tab`           VARCHAR(255) NOT NULL DEFAULT 'Sheet1',
                    `lead_status_id`      INT(11) NOT NULL DEFAULT 0,
                    `lead_source_id`      INT(11) NOT NULL DEFAULT 0,
                    `default_assignee`    INT(11) NOT NULL DEFAULT 0,
                    `column_mapping`      TEXT NULL,
                    `description_columns` TEXT NULL,
                    `id_column`           VARCHAR(100) NOT NULL DEFAULT 'id',
                    `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
                    `last_run_at`         DATETIME NULL DEFAULT NULL,
                    `created_at`          DATETIME NULL DEFAULT NULL,
                    `updated_at`          DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        } else {
            // Add missing columns to existing table
            if (!$this->db->field_exists('default_assignee', $t)) {
                $this->db->query("ALTER TABLE `{$t}` ADD COLUMN `default_assignee` INT(11) NOT NULL DEFAULT 0 AFTER `lead_source_id`");
            }
            if (!$this->db->field_exists('last_run_at', $t)) {
                $this->db->query("ALTER TABLE `{$t}` ADD COLUMN `last_run_at` DATETIME NULL DEFAULT NULL AFTER `is_active`");
            }
        }

        if (!$this->db->table_exists($p . 'gs_lead_sync_imported')) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$p}gs_lead_sync_imported` (
                    `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `sheet_config_id` INT(11) UNSIGNED NOT NULL,
                    `row_lead_id`     VARCHAR(191) NOT NULL DEFAULT '',
                    `perfex_lead_id`  INT(11) NOT NULL DEFAULT 0,
                    `imported_at`     DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_import` (`sheet_config_id`, `row_lead_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        if (!$this->db->table_exists($p . 'gs_lead_sync_logs')) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `{$p}gs_lead_sync_logs` (
                    `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `sheet_config_id` INT(11) NOT NULL DEFAULT 0,
                    `triggered_by`    VARCHAR(50) NOT NULL DEFAULT 'manual',
                    `rows_fetched`    INT(11) NOT NULL DEFAULT 0,
                    `rows_imported`   INT(11) NOT NULL DEFAULT 0,
                    `rows_skipped`    INT(11) NOT NULL DEFAULT 0,
                    `rows_failed`     INT(11) NOT NULL DEFAULT 0,
                    `error_details`   TEXT NULL,
                    `started_at`      DATETIME NULL DEFAULT NULL,
                    `finished_at`     DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ");
        }

        $this->db->db_debug = $d;
    }
}
