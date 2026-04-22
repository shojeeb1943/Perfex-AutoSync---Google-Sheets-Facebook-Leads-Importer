<?php
defined('BASEPATH') or exit('No direct script access allowed');

class LeadMapper
{
    // Maps CRM field keys to human-readable labels shown in the mapping UI
    public static $crm_fields = [
        'name'        => 'Name (required)',
        'email'       => 'Email Address',
        'phonenumber' => 'Phone Number',
        'company'     => 'Company',
        'title'       => 'Title / Job Title',
        'address'     => 'Address',
        'city'        => 'City',
        'country'     => 'Country',
        'zip'         => 'Zip Code',
        'website'     => 'Website',
        'lead_value'  => 'Lead Value',
        'description' => 'Description / Notes',
    ];

    /**
     * Map a single sheet row to a Perfex CRM lead data array.
     *
     * @param array  $header              The sheet header row
     * @param array  $row_values          The data row values
     * @param array  $column_mapping      JSON-decoded mapping: {crm_field => sheet_column_name}
     * @param array  $description_columns Array of sheet column names to concat into description
     * @param bool   $skip_test_leads     Whether to skip rows with <test lead: markers
     * @return array|null Lead data array, or null if row should be skipped
     */
    public static function map_row($header, $row_values, $column_mapping, $description_columns = [], $skip_test_leads = true)
    {
        // Build column index map: column_name => index
        $col_index = [];
        foreach ($header as $i => $col_name) {
            $col_index[trim($col_name)] = $i;
        }

        // Helper: get value by column name
        $get_value = function ($col_name) use ($col_index, $row_values) {
            $col_name = trim($col_name);
            if (!isset($col_index[$col_name])) {
                return '';
            }
            $idx = $col_index[$col_name];
            return isset($row_values[$idx]) ? trim($row_values[$idx]) : '';
        };

        // Test lead detection — check all values in the row
        if ($skip_test_leads) {
            foreach ($row_values as $cell) {
                if (strpos((string)$cell, '<test lead:') !== false) {
                    return null;
                }
            }
        }

        // Map standard CRM fields from column_mapping
        $lead = [];
        foreach ($column_mapping as $crm_field => $sheet_col) {
            if (empty($sheet_col)) {
                continue;
            }
            $value = $get_value($sheet_col);
            if ($value === '') {
                continue;
            }
            // Phone cleanup: strip leading "p:" prefix Facebook adds
            if ($crm_field === 'phonenumber') {
                $value = preg_replace('/^p:/i', '', $value);
            }
            $lead[$crm_field] = $value;
        }

        // name is required
        if (empty($lead['name'])) {
            return null;
        }

        // Build description from description_columns
        if (!empty($description_columns)) {
            $desc_parts = [];
            foreach ($description_columns as $col_name) {
                $value = $get_value($col_name);
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
