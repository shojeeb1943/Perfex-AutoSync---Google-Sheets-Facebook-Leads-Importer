<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('gs_lead_sync_install')) {
function gs_lead_sync_install()
{
    $CI = &get_instance();
    $CI->load->dbforge();

    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_sheets')) {
        $CI->dbforge->add_field([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 255],
            'spreadsheet_id'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'sheet_tab'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Sheet1'],
            'lead_status_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'lead_source_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'default_assignee'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'column_mapping'      => ['type' => 'TEXT', 'null' => true],
            'description_columns' => ['type' => 'TEXT', 'null' => true],
            'id_column'           => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'id'],
            'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_run_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $CI->dbforge->add_key('id', true);
        $CI->dbforge->create_table(db_prefix() . 'gs_lead_sync_sheets', true);
    } else {
        if (!$CI->db->field_exists('last_run_at', db_prefix() . 'gs_lead_sync_sheets')) {
            $CI->dbforge->add_column(db_prefix() . 'gs_lead_sync_sheets', [
                'last_run_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
        if (!$CI->db->field_exists('default_assignee', db_prefix() . 'gs_lead_sync_sheets')) {
            $CI->dbforge->add_column(db_prefix() . 'gs_lead_sync_sheets', [
                'default_assignee' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            ]);
        }
    }

    // Raw CREATE TABLE so the UNIQUE KEY is defined in one atomic statement —
    // avoids the separate ALTER TABLE that would throw if the key already exists.
    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_imported')) {
        $CI->db->query('CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'gs_lead_sync_imported` (
            `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `sheet_config_id` INT(11) UNSIGNED NOT NULL,
            `row_lead_id`     VARCHAR(255) NOT NULL DEFAULT \'\',
            `perfex_lead_id`  INT(11) NULL DEFAULT NULL,
            `imported_at`     DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_import` (`sheet_config_id`, `row_lead_id`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        // Upgrade: add the unique key if it was missing from an older install.
        $idx = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "gs_lead_sync_imported` WHERE Key_name = 'unique_import'");
        if ($idx && $idx->num_rows() === 0) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'gs_lead_sync_imported` ADD UNIQUE KEY `unique_import` (`sheet_config_id`, `row_lead_id`(191))');
        }
    }

    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_logs')) {
        $CI->dbforge->add_field([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sheet_config_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'triggered_by'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'manual'],
            'rows_fetched'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'rows_imported'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'rows_skipped'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'rows_failed'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'error_details'   => ['type' => 'TEXT', 'null' => true],
            'started_at'      => ['type' => 'DATETIME', 'null' => true],
            'finished_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $CI->dbforge->add_key('id', true);
        $CI->dbforge->create_table(db_prefix() . 'gs_lead_sync_logs', true);
    }
}
}

if (!function_exists('gs_lead_sync_uninstall')) {
function gs_lead_sync_uninstall()
{
    $CI = &get_instance();
    $CI->load->dbforge();
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_logs',    true);
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_imported', true);
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_sheets',   true);
}
}
