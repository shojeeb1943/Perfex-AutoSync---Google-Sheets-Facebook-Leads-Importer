<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_LeadMapper
{
    public static $crm_fields = array(
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
    );

    public static function map_row($header, $row_values, $column_mapping, $description_columns = array(), $skip_test_leads = true)
    {
        $row_values = array_pad($row_values, count($header), '');

        $col_index = array();
        foreach ($header as $i => $col_name) {
            $col_index[trim($col_name)] = $i;
        }

        $get_value = function ($col_name) use ($col_index, $row_values) {
            $col_name = trim($col_name);
            if (!isset($col_index[$col_name])) { return ''; }
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

        $lead = array();
        foreach ($column_mapping as $crm_field => $sheet_col) {
            if (empty($sheet_col)) { continue; }
            if (!isset(self::$crm_fields[$crm_field])) { continue; }

            $value = $get_value($sheet_col);
            if ($value === '') { continue; }

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
            $desc_parts = array();
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
