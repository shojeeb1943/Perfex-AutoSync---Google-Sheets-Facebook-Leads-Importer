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

register_activation_hook(GS_LEAD_SYNC_MODULE_NAME, 'gs_lead_sync_activate');
register_uninstall_hook(GS_LEAD_SYNC_MODULE_NAME,  'gs_lead_sync_uninstall');

hooks()->add_action('admin_init',     'gs_lead_sync_init_menu');
hooks()->add_action('app_admin_head', 'gs_lead_sync_head');
hooks()->add_action('app_cron',       'gs_lead_sync_cron');

/* ------------------------------------------------------------------ */
/*  SIDEBAR MENU                                                      */
/* ------------------------------------------------------------------ */
function gs_lead_sync_init_menu()
{
    $CI =& get_instance();
    if (!function_exists('is_admin') || !is_admin()) { return; }
    if (!isset($CI->app_menu)) { return; }
    if (!method_exists($CI->app_menu, 'add_sidebar_menu_item')) { return; }

    $CI->app_menu->add_sidebar_menu_item('gs-lead-sync', array(
        'name'     => 'GS Lead Sync',
        'href'     => admin_url('gs_lead_sync'),
        'icon'     => 'fa fa-table',
        'position' => 35,
    ));
}

/* ------------------------------------------------------------------ */
/*  ASSETS                                                            */
/* ------------------------------------------------------------------ */
function gs_lead_sync_head()
{
    $CI =& get_instance();
    if (!isset($CI->uri)) { return; }
    $uri = $CI->uri->uri_string();
    if (strpos($uri, 'gs_lead_sync') === false) { return; }

    $b = base_url('modules/gs_lead_sync/');
    echo '<link rel="stylesheet" href="' . $b . 'assets/css/gs_lead_sync.css">' . "\n";
    echo '<script src="' . $b . 'assets/js/gs_lead_sync.js"></script>' . "\n";
}

/* ------------------------------------------------------------------ */
/*  CRON                                                              */
/* ------------------------------------------------------------------ */
function gs_lead_sync_cron()
{
    if (get_option('gs_lead_sync_cron_enabled') != '1') { return; }

    $CI =& get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_sheets')) { return; }

    $CI->load->model('gs_lead_sync/sheet_config_model');
    $CI->load->model('gs_lead_sync/sync_log_model');
    require_once GS_LEAD_SYNC_DIR . 'libraries/LeadMapper.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/GoogleSheetsClient.php';
    require_once GS_LEAD_SYNC_DIR . 'libraries/SyncEngine.php';

    $interval = gs_lead_sync_interval_seconds(get_option('gs_lead_sync_cron_interval'));
    $now      = time();
    $sheets   = $CI->sheet_config_model->get_active_sheets();

    foreach ($sheets as $s) {
        $last = !empty($s['last_run_at']) ? strtotime($s['last_run_at']) : 0;
        if ($last && ($now - $last) < $interval) { continue; }
        try {
            $engine = new Gs_SyncEngine();
            $engine->sync_sheet((int)$s['id'], 'cron');
        } catch (Exception $e) {
            log_message('error', 'gs_lead_sync cron: ' . $e->getMessage());
        }
    }
}

function gs_lead_sync_interval_seconds($k)
{
    $m = array('15min'=>900,'30min'=>1800,'1hr'=>3600,'6hr'=>21600,'daily'=>86400);
    return isset($m[$k]) ? $m[$k] : 3600;
}

/* ------------------------------------------------------------------ */
/*  ACTIVATION — creates DB tables using raw SQL (safest approach)    */
/* ------------------------------------------------------------------ */
function gs_lead_sync_activate()
{
    $CI =& get_instance();
    $p  = db_prefix();

    // Table 1: sheet configurations
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `{$p}gs_lead_sync_sheets` (
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

    // Table 2: dedup tracker
    $CI->db->query("
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

    // Table 3: sync run logs
    $CI->db->query("
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

    // Seed options
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

/* ------------------------------------------------------------------ */
/*  UNINSTALL                                                         */
/* ------------------------------------------------------------------ */
function gs_lead_sync_uninstall()
{
    $CI =& get_instance();
    $p  = db_prefix();
    $CI->db->query("DROP TABLE IF EXISTS `{$p}gs_lead_sync_logs`");
    $CI->db->query("DROP TABLE IF EXISTS `{$p}gs_lead_sync_imported`");
    $CI->db->query("DROP TABLE IF EXISTS `{$p}gs_lead_sync_sheets`");
}
