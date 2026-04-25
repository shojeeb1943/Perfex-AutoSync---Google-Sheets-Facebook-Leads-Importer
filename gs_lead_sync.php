<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Google Sheets Lead Sync
Description: Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
Version: 1.3.2
Requires at least: 2.3.*
Author: ByteSIS
Author URI: https://bytesis.com
*/

defined('GS_LEAD_SYNC_MODULE_NAME') or define('GS_LEAD_SYNC_MODULE_NAME', 'gs_lead_sync');
defined('GS_LEAD_SYNC_VERSION')     or define('GS_LEAD_SYNC_VERSION',     '1.3.2');
defined('GS_LEAD_SYNC_DIR')         or define('GS_LEAD_SYNC_DIR',         dirname(__FILE__) . '/');

register_activation_hook(GS_LEAD_SYNC_MODULE_NAME, 'gs_lead_sync_activation_hook');
register_uninstall_hook(GS_LEAD_SYNC_MODULE_NAME,  'gs_lead_sync_uninstall_hook');

hooks()->add_action('app_admin_head', 'gs_lead_sync_assets');
hooks()->add_action('app_cron',       'gs_lead_sync_cron');
hooks()->add_action('admin_init',     'gs_lead_sync_menu');
hooks()->add_action('admin_init',     'gs_lead_sync_ensure_schema');

function gs_lead_sync_assets()
{
    $CI =& get_instance();
    if (strpos($CI->uri->uri_string(), 'gs_lead_sync') === false) {
        return;
    }
    $module_uri = base_url('modules/gs_lead_sync/');
    echo '<link rel="stylesheet" href="' . $module_uri . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="'                 . $module_uri . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

function gs_lead_sync_menu()
{
    $CI =& get_instance();
    if (!is_admin()) {
        return;
    }
    if (!isset($CI->app_menu) || !is_object($CI->app_menu)
        || !method_exists($CI->app_menu, 'add_sidebar_menu_item')) {
        return;
    }
    $CI->app_menu->add_sidebar_menu_item('gs-lead-sync', [
        'name'     => 'GS Lead Sync',
        'href'     => admin_url('gs_lead_sync'),
        'icon'     => 'fa fa-table',
        'position' => 35,
    ]);
}

function gs_lead_sync_cron()
{
    if (get_option('gs_lead_sync_cron_enabled') != '1') {
        return;
    }

    $CI =& get_instance();
    $CI->load->model('gs_lead_sync/sheet_config_model');
    $CI->load->model('gs_lead_sync/sync_log_model');
    require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

    $interval_seconds = gs_lead_sync_interval_seconds(get_option('gs_lead_sync_cron_interval'));
    $now              = time();

    $sheets = $CI->sheet_config_model->get_active_sheets();
    $engine = new Gs_SyncEngine();

    foreach ($sheets as $sheet) {
        $last_run = !empty($sheet['last_run_at']) ? strtotime($sheet['last_run_at']) : 0;
        if ($last_run && ($now - $last_run) < $interval_seconds) {
            continue;
        }
        $engine->sync_sheet((int)$sheet['id'], 'cron');
    }
}

function gs_lead_sync_interval_seconds($key)
{
    switch ($key) {
        case '15min': return 900;
        case '30min': return 1800;
        case '1hr':   return 3600;
        case '6hr':   return 21600;
        case 'daily': return 86400;
        default:      return 3600;
    }
}

function gs_lead_sync_ensure_schema()
{
    static $ran = false;
    if ($ran) { return; }
    $ran = true;

    $CI =& get_instance();

    // Suppress db_debug so SHOW TABLES/COLUMNS never trigger show_error() → exit().
    $prev = $CI->db->db_debug;
    $CI->db->db_debug = false;

    $table_ok = $CI->db->table_exists(db_prefix() . 'gs_lead_sync_sheets');

    $CI->db->db_debug = $prev;

    if (!$table_ok) {
        return;
    }

    $prev = $CI->db->db_debug;
    $CI->db->db_debug = false;
    $has_assignee   = $CI->db->field_exists('default_assignee', db_prefix() . 'gs_lead_sync_sheets');
    $has_last_run   = $CI->db->field_exists('last_run_at',       db_prefix() . 'gs_lead_sync_sheets');
    $CI->db->db_debug = $prev;

    if (!$has_assignee || !$has_last_run) {
        require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
        gs_lead_sync_install(); // db_debug already suppressed internally
    }
}

function gs_lead_sync_activation_hook()
{
    try {
        require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
        gs_lead_sync_install();
    } catch (Throwable $e) {
        log_message('error', 'gs_lead_sync activation: ' . $e->getMessage());
    }

    if (get_option('gs_lead_sync_skip_test_leads') === '') {
        add_option('gs_lead_sync_skip_test_leads', '1');
    }
    if (get_option('gs_lead_sync_cron_enabled') === '') {
        add_option('gs_lead_sync_cron_enabled', '0');
    }
    if (get_option('gs_lead_sync_cron_interval') === '') {
        add_option('gs_lead_sync_cron_interval', '1hr');
    }
}

function gs_lead_sync_uninstall_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_uninstall();
}
