<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Google Sheets Lead Sync
Description: Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
Version: 1.3.0
Requires at least: 2.3.*
Author: ByteSIS
Author URI: https://bytesis.com
*/

defined('GS_LEAD_SYNC_MODULE_NAME') or define('GS_LEAD_SYNC_MODULE_NAME', 'gs_lead_sync');
defined('GS_LEAD_SYNC_VERSION')     or define('GS_LEAD_SYNC_VERSION',     '1.3.0');
defined('GS_LEAD_SYNC_DIR')         or define('GS_LEAD_SYNC_DIR',         dirname(__FILE__) . '/');
defined('GS_LEAD_SYNC_URI')         or define('GS_LEAD_SYNC_URI',         base_url('modules/gs_lead_sync/'));

if (!function_exists('gs_lead_sync_activation_hook')) {
    register_activation_hook(GS_LEAD_SYNC_MODULE_NAME, 'gs_lead_sync_activation_hook');
    register_uninstall_hook(GS_LEAD_SYNC_MODULE_NAME,  'gs_lead_sync_uninstall_hook');
    hooks()->add_action('app_admin_head', 'gs_lead_sync_assets');
    hooks()->add_action('app_cron',       'gs_lead_sync_cron');
    hooks()->add_action('admin_init',     'gs_lead_sync_menu');
}

/**
 * Inject CSS/JS only on this module's admin pages.
 * Previously fired on every admin request, bleeding label styles into the
 * entire CRM and risking JSON-response contamination on AJAX endpoints.
 */
function gs_lead_sync_assets()
{
    $CI =& get_instance();
    // Perfex admin URLs are /admin/gs_lead_sync/... — segment(1)='admin',
    // segment(2)='gs_lead_sync'. Match on segment(2) (or anywhere in the URI
    // for safety against routing customisations).
    $uri = $CI->uri->uri_string();
    if (strpos($uri, 'gs_lead_sync') === false) {
        return;
    }
    echo '<link rel="stylesheet" href="' . GS_LEAD_SYNC_URI . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="'                 . GS_LEAD_SYNC_URI . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

function gs_lead_sync_menu()
{
    $CI =& get_instance();
    if (is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('gs-lead-sync', [
            'name'     => 'GS Lead Sync',
            'href'     => admin_url('gs_lead_sync'),
            'icon'     => 'fa fa-google',
            'position' => 50,
        ]);
    }
}

/**
 * Cron entry. Perfex fires app_cron roughly every 5 minutes; we filter each
 * sheet against its configured interval so we don't spam the Google API.
 */
function gs_lead_sync_cron()
{
    if (get_option('gs_lead_sync_cron_enabled') != '1') {
        return;
    }

    $CI =& get_instance();
    $CI->load->model('gs_lead_sync/sheet_config_model');
    $CI->load->model('gs_lead_sync/sync_log_model');
    $CI->load->model('leads_model');
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
        $CI->sheet_config_model->mark_run((int)$sheet['id']);
    }
}

/**
 * Map cron interval option to seconds.
 */
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

function gs_lead_sync_activation_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_install();

    if (get_option('gs_lead_sync_skip_test_leads') === '') {
        update_option('gs_lead_sync_skip_test_leads', '1');
    }
    if (get_option('gs_lead_sync_cron_enabled') === '') {
        update_option('gs_lead_sync_cron_enabled', '0');
    }
    if (get_option('gs_lead_sync_cron_interval') === '') {
        update_option('gs_lead_sync_cron_interval', '1hr');
    }
}

function gs_lead_sync_uninstall_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_uninstall();
}
