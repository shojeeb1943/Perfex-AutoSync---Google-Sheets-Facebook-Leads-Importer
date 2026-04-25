<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Database installation / uninstallation for gs_lead_sync module.
 *
 * These functions are guarded with function_exists() so the file can be
 * safely loaded multiple times (Perfex's migration scanner + activation hook).
 */

if (!function_exists('gs_lead_sync_install')) {
    function gs_lead_sync_install()
    {
        $CI = &get_instance();
        $CI->load->dbforge();

        $prefix = db_prefix();

        // ── Sheets config table ─────────────────────────────────────────
        if (!$CI->db->table_exists($prefix . 'gs_lead_sync_sheets')) {
            $CI->dbforge->add_field([
                'id'                  => ['type' => 'INT',     'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'name'                => ['type' => 'VARCHAR', 'constraint' => 255],
                'spreadsheet_id'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'sheet_tab'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Sheet1'],
                'lead_status_id'      => ['type' => 'INT',     'constraint' => 11,  'null' => true],
                'lead_source_id'      => ['type' => 'INT',     'constraint' => 11,  'null' => true],
                'default_assignee'    => ['type' => 'INT',     'constraint' => 11,  'null' => true],
                'column_mapping'      => ['type' => 'TEXT',    'null' => true],
                'description_columns' => ['type' => 'TEXT',    'null' => true],
                'id_column'           => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'id'],
                'is_active'           => ['type' => 'TINYINT', 'constraint' => 1,   'default' => 1],
                'last_run_at'         => ['type' => 'DATETIME', 'null' => true],
                'created_at'          => ['type' => 'DATETIME', 'null' => true],
                'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $CI->dbforge->add_key('id', true);
            $CI->dbforge->create_table($prefix . 'gs_lead_sync_sheets', true);
        } else {
            // Upgrade path: add columns introduced after the initial release
            if (!$CI->db->field_exists('last_run_at', $prefix . 'gs_lead_sync_sheets')) {
                $CI->dbforge->add_column($prefix . 'gs_lead_sync_sheets', [
                    'last_run_at' => ['type' => 'DATETIME', 'null' => true],
                ]);
            }
            if (!$CI->db->field_exists('default_assignee', $prefix . 'gs_lead_sync_sheets')) {
                $CI->dbforge->add_column($prefix . 'gs_lead_sync_sheets', [
                    'default_assignee' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                ]);
            }
        }

        // ── Imported leads dedup table ──────────────────────────────────
        if (!$CI->db->table_exists($prefix . 'gs_lead_sync_imported')) {
            $CI->db->query('CREATE TABLE IF NOT EXISTS `' . $prefix . 'gs_lead_sync_imported` (
                `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `sheet_config_id` INT(11) UNSIGNED NOT NULL,
                `row_lead_id`     VARCHAR(255) NOT NULL DEFAULT \'\',
                `perfex_lead_id`  INT(11) NULL DEFAULT NULL,
                `imported_at`     DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_import` (`sheet_config_id`, `row_lead_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        // ── Sync run logs table ─────────────────────────────────────────
        if (!$CI->db->table_exists($prefix . 'gs_lead_sync_logs')) {
            $CI->dbforge->add_field([
                'id'              => ['type' => 'INT',     'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'sheet_config_id' => ['type' => 'INT',     'constraint' => 11, 'null' => true],
                'triggered_by'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'manual'],
                'rows_fetched'    => ['type' => 'INT',     'constraint' => 11, 'default' => 0],
                'rows_imported'   => ['type' => 'INT',     'constraint' => 11, 'default' => 0],
                'rows_skipped'    => ['type' => 'INT',     'constraint' => 11, 'default' => 0],
                'rows_failed'     => ['type' => 'INT',     'constraint' => 11, 'default' => 0],
                'error_details'   => ['type' => 'TEXT',    'null' => true],
                'started_at'      => ['type' => 'DATETIME', 'null' => true],
                'finished_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $CI->dbforge->add_key('id', true);
            $CI->dbforge->create_table($prefix . 'gs_lead_sync_logs', true);
        }
    }
}

if (!function_exists('gs_lead_sync_uninstall')) {
    function gs_lead_sync_uninstall()
    {
        $CI = &get_instance();
        $CI->load->dbforge();
        $prefix = db_prefix();

        $CI->dbforge->drop_table($prefix . 'gs_lead_sync_logs',    true);
        $CI->dbforge->drop_table($prefix . 'gs_lead_sync_imported', true);
        $CI->dbforge->drop_table($prefix . 'gs_lead_sync_sheets',   true);
    }
}
