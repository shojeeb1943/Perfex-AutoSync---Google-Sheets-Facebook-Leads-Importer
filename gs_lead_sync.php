<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Google Sheets Lead Sync
Description: Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
Version: 1.2.0
Requires at least: 2.3.*
Author: ByteSIS
Author URI: https://bytesis.com
*/

define('GS_LEAD_SYNC_MODULE_NAME', 'gs_lead_sync');
define('GS_LEAD_SYNC_VERSION',     '1.2.0');
define('GS_LEAD_SYNC_DIR',         dirname(__FILE__) . '/');
define('GS_LEAD_SYNC_URI',         base_url('modules/gs_lead_sync/'));

register_language_files(GS_LEAD_SYNC_MODULE_NAME, [GS_LEAD_SYNC_MODULE_NAME]);

register_activation_hook(GS_LEAD_SYNC_MODULE_NAME,   'gs_lead_sync_activation_hook');
register_deactivation_hook(GS_LEAD_SYNC_MODULE_NAME, 'gs_lead_sync_deactivation_hook');

hooks()->add_action('app_admin_head', 'gs_lead_sync_assets');
hooks()->add_action('app_cron',       'gs_lead_sync_cron');
hooks()->add_action('admin_init',     'gs_lead_sync_menu');

function gs_lead_sync_assets()
{
    echo '<link rel="stylesheet" href="' . GS_LEAD_SYNC_URI . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="'                 . GS_LEAD_SYNC_URI . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

function gs_lead_sync_menu()
{
    $CI = &get_instance();
    if (is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('gs-lead-sync', [
            'name'     => 'GS Lead Sync',
            'href'     => admin_url('gs_lead_sync'),
            'icon'     => 'fa fa-google',
            'position' => 50,
        ]);
    }
}

function gs_lead_sync_cron()
{
    if (get_option('gs_lead_sync_cron_enabled') != '1') {
        return;
    }
    $CI = &get_instance();
    $CI->load->model('gs_lead_sync/sheet_config_model');
    $CI->load->model('gs_lead_sync/sync_log_model');
    require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';
    $engine = new SyncEngine();
    $engine->sync_all('cron');
}

function gs_lead_sync_activation_hook()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_install();

    // Only set defaults on first activation (don't overwrite user choices on re-activate)
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

function gs_lead_sync_deactivation_hook()
{
    // Intentionally blank — preserve all data on deactivation
}
