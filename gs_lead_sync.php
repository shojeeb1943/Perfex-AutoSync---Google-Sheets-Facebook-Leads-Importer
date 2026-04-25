<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Google Sheets Lead Sync
Description: Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
Version: 1.4.0
Requires at least: 2.3.*
Author: ByteSIS
Author URI: https://bytesis.com
*/

defined('GS_LEAD_SYNC_MODULE_NAME') or define('GS_LEAD_SYNC_MODULE_NAME', 'gs_lead_sync');
defined('GS_LEAD_SYNC_VERSION')     or define('GS_LEAD_SYNC_VERSION',     '1.4.0');
defined('GS_LEAD_SYNC_DIR')         or define('GS_LEAD_SYNC_DIR',         __DIR__ . '/');

// ── Activation / Uninstall ──────────────────────────────────────────────────
register_activation_hook(GS_LEAD_SYNC_MODULE_NAME, 'gs_lead_sync_activation_hook');
register_uninstall_hook(GS_LEAD_SYNC_MODULE_NAME,  'gs_lead_sync_uninstall_hook');

// ── Runtime hooks ───────────────────────────────────────────────────────────
hooks()->add_action('admin_init',      'gs_lead_sync_menu');
hooks()->add_action('app_admin_head',  'gs_lead_sync_assets');
hooks()->add_action('app_cron',        'gs_lead_sync_cron');

/**
 * Add sidebar menu item — only for admins.
 */
function gs_lead_sync_menu()
{
    $CI =& get_instance();
    if (!function_exists('is_admin') || !is_admin()) {
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

/**
 * Inject CSS/JS only on this module's admin pages.
 */
function gs_lead_sync_assets()
{
    $CI =& get_instance();
    if (!isset($CI->uri) || !is_object($CI->uri)) {
        return;
    }
    if (strpos($CI->uri->uri_string(), 'gs_lead_sync') === false) {
        return;
    }
    $base = base_url('modules/gs_lead_sync/');
    echo '<link rel="stylesheet" href="' . $base . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="' . $base . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

/**
 * Cron entry — Perfex fires app_cron every ~5 min; we gate per-sheet.
 */
function gs_lead_sync_cron()
{
    if (get_option('gs_lead_sync_cron_enabled') != '1') {
        return;
    }

    $CI =& get_instance();

    // Ensure our tables exist before doing anything
    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_sheets')) {
        return;
    }

    $CI->load->model('gs_lead_sync/sheet_config_model');
    $CI->load->model('gs_lead_sync/sync_log_model');

    require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

    $interval = gs_lead_sync_interval_seconds(get_option('gs_lead_sync_cron_interval'));
    $now      = time();
    $sheets   = $CI->sheet_config_model->get_active_sheets();

    foreach ($sheets as $sheet) {
        $last = !empty($sheet['last_run_at']) ? strtotime($sheet['last_run_at']) : 0;
        if ($last && ($now - $last) < $interval) {
            continue;
        }
        try {
            $engine = new Gs_SyncEngine();
            $engine->sync_sheet((int) $sheet['id'], 'cron');
        } catch (Exception $e) {
            log_message('error', 'gs_lead_sync cron error sheet #' . $sheet['id'] . ': ' . $e->getMessage());
        }
    }
}

/**
 * Map interval key → seconds.
 */
function gs_lead_sync_interval_seconds($key)
{
    $map = [
        '15min' => 900,
        '30min' => 1800,
        '1hr'   => 3600,
        '6hr'   => 21600,
        'daily' => 86400,
    ];
    return isset($map[$key]) ? $map[$key] : 3600;
}

// ── Activation ──────────────────────────────────────────────────────────────
function gs_lead_sync_activation_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_install();

    // Seed default option values (only if they don't exist yet)
    $defaults = [
        'gs_lead_sync_skip_test_leads' => '1',
        'gs_lead_sync_cron_enabled'    => '0',
        'gs_lead_sync_cron_interval'   => '1hr',
    ];
    foreach ($defaults as $key => $val) {
        if (get_option($key) === false || get_option($key) === '') {
            add_option($key, $val);
        }
    }
}

function gs_lead_sync_uninstall_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_uninstall();
}
