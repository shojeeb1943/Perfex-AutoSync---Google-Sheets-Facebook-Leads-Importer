<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Google Sheets Lead Sync
Description: Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
Version: 1.2.0
Author: ByteSIS
Author URI: https://bytesis.com
Requires at least: 2.3.*
*/

define('GS_LEAD_SYNC_MODULE_NAME', 'gs_lead_sync');
define('GS_LEAD_SYNC_VERSION', '1.2.0');
define('GS_LEAD_SYNC_AUTHOR', 'ByteSIS — https://bytesis.com');
define('GS_LEAD_SYNC_DIR', module_dir_path(GS_LEAD_SYNC_MODULE_NAME));
define('GS_LEAD_SYNC_URI', module_dir_url(GS_LEAD_SYNC_MODULE_NAME));

hooks()->add_action('app_admin_head', 'gs_lead_sync_assets');
hooks()->add_action('app_cron', 'gs_lead_sync_cron');
hooks()->add_action('admin_init', 'gs_lead_sync_menu');

function gs_lead_sync_assets()
{
    echo '<link rel="stylesheet" href="' . GS_LEAD_SYNC_URI . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="' . GS_LEAD_SYNC_URI . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

function gs_lead_sync_menu()
{
    $CI = &get_instance();
    if (is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('gs-lead-sync', [
            'slug'     => 'gs-lead-sync',
            'name'     => 'GS Lead Sync',
            'icon'     => 'fa fa-google',
            'href'     => admin_url('gs_lead_sync'),
            'position' => 50,
            'type'     => 'link',
        ]);
    }
}

function gs_lead_sync_cron()
{
    $CI = &get_instance();
    if (get_option('gs_lead_sync_cron_enabled') != '1') {
        return;
    }
    $CI->load->library('gs_lead_sync/SyncEngine');
    $CI->syncengine->sync_all('cron');
}

function activate_gs_lead_sync()
{
    require_once GS_LEAD_SYNC_DIR . 'migrations/001_install_gs_lead_sync.php';
    gs_lead_sync_install();

    if (!get_option('gs_lead_sync_skip_test_leads')) {
        update_option('gs_lead_sync_skip_test_leads', '1');
    }
    if (!get_option('gs_lead_sync_cron_enabled')) {
        update_option('gs_lead_sync_cron_enabled', '0');
    }
    if (!get_option('gs_lead_sync_cron_interval')) {
        update_option('gs_lead_sync_cron_interval', '1hr');
    }
}

function deactivate_gs_lead_sync()
{
    // Intentionally blank — preserve all data on deactivation
}
