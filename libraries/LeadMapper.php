<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_LeadMapper
{
    // Maps CRM field keys to human-readable labels shown in the mapping UI.
    // Values here are also used as the whitelist for columns handed to
    // leads_model::add() — do not add keys that aren't real Perfex lead columns.
    public static $crm_fields = [
        'name'        => 'Name (required)',
        'email'       => 'Email Address',
        'phonenumber' => 'Phone Number',
        'company'     => 'Company',
        'title'       => 'Title / Job Title',
        'address'     => 'Address',
        'city'        => 'City',
        'state'       => 'State',
        'country'     => 'Country',
        'zip'         => 'Zip Code',
        'website'     => 'Website',
        'lead_value'  => 'Lead Value',
        'description' => 'Description / Notes',
    ];

    /**
     * Map a single sheet row to a Perfex CRM lead data array.
     *
     * @param array $header              Sheet header row
     * @param array $row_values          Data row (will be padded to header length)
     * @param array $column_mapping      {crm_field => sheet_column_name}
     * @param array $description_columns Sheet columns to concat into description
     * @param bool  $skip_test_leads     Whether to skip Facebook test-lead rows
     * @return array|null Lead data array, or null if the row should be skipped
     */
    public static function map_row($header, $row_values, $column_mapping, $description_columns = [], $skip_test_leads = true)
    {
        // Google Sheets API returns sparse arrays when trailing cells are
        // empty. Pad so positional lookups by header index never miss.
        $row_values = array_pad($row_values, count($header), '');

        $col_index = [];
        foreach ($header as $i => $col_name) {
            $col_index[trim($col_name)] = $i;
        }

        $get_value = function ($col_name) use ($col_index, $row_values) {
            $col_name = trim($col_name);
            if (!isset($col_index[$col_name])) {
                return '';
            }
            $idx = $col_index[$col_name];
            return isset($row_values[$idx]) ? trim($row_values[$idx]) : '';
        };

        if ($skip_test_leads) {
            foreach ($row_values as $cell) {
                if (strpos((string)$cell, '<test lead:') !== false) {
                    return null;
                }
            }
        }

        $lead = [];
        foreach ($column_mapping as $crm_field => $sheet_col) {
            if (empty($sheet_col)) {
                continue;
            }
            if (!isset(self::$crm_fields[$crm_field])) {
                // Unknown CRM key — ignore rather than let it flow to insert.
                continue;
            }
            $value = $get_value($sheet_col);
            if ($value === '') {
                continue;
            }
            // Strip tags to prevent XSS from external sheet data.
            $value = strip_tags($value);
            if ($crm_field === 'phonenumber') {
                $value = preg_replace('/^p:/i', '', $value);
            }
            if ($crm_field === 'lead_value') {
                $value = preg_replace('/[^0-9.]/', '', $value);
            }
            $lead[$crm_field] = $value;
        }

        if (empty($lead['name'])) {
            return null;
        }

        if (!empty($description_columns)) {
            $desc_parts = [];
            foreach ($description_columns as $col_name) {
                $value = strip_tags($get_value($col_name));
                if ($value !== '') {
                    $desc_parts[] = $col_name . ': ' . $value;
                }
            }
            if (!empty($desc_parts)) {
                $existing = isset($lead['description']) ? $lead['description'] . "\n" : '';
                $lead['description'] = $existing . implode("\n", $desc_parts);
            }
        }

        return $lead;
    }
}
