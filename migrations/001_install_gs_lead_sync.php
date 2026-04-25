<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Wraps the install/uninstall helpers in a CI_Migration class so Perfex's
 * "Upgrade Database" scanner can load this file without a fatal error.
 * The activation/uninstall hooks also call these helpers directly.
 */
class Migration_Install_gs_lead_sync extends CI_Migration
{
    public function up()
    {
        gs_lead_sync_install();
    }

    public function down()
    {
        gs_lead_sync_uninstall();
    }
}

// ---------------------------------------------------------------------------
// Standalone helpers — called by the activation/uninstall hooks as well as
// by the CI_Migration class above.
// ---------------------------------------------------------------------------

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
        // Upgrade path: add columns introduced after the initial release.
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

    if (!$CI->db->table_exists(db_prefix() . 'gs_lead_sync_imported')) {
        $CI->dbforge->add_field([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sheet_config_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'row_lead_id'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'perfex_lead_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'imported_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $CI->dbforge->add_key('id', true);
        $CI->dbforge->create_table(db_prefix() . 'gs_lead_sync_imported', true);
        $CI->db->query('ALTER TABLE ' . db_prefix() . 'gs_lead_sync_imported ADD UNIQUE KEY unique_import (sheet_config_id, row_lead_id(191))');
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

function gs_lead_sync_uninstall()
{
    $CI = &get_instance();
    $CI->load->dbforge();
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_logs',    true);
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_imported', true);
    $CI->dbforge->drop_table(db_prefix() . 'gs_lead_sync_sheets',   true);
}
