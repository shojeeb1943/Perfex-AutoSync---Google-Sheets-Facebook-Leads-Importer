/**
 * gs_lead_sync — frontend JS for sheet form and Detect Columns AJAX
 */

(function ($) {
    'use strict';

    // Called on page load in edit mode (with pre-detected columns) and after AJAX detection
    window.gsPopulateSelects = function (columns) {
        if (!columns || columns.length === 0) return;

        // Populate each mapping dropdown
        $('.gs-col-select').each(function () {
            var select    = $(this);
            var savedVal  = select.val(); // preserve existing saved selection
            var options   = '<option value="">— Skip —</option>';
            $.each(columns, function (i, col) {
                options += '<option value="' + gsEsc(col) + '">' + gsEsc(col) + '</option>';
            });
            select.html(options);
            if (savedVal) {
                select.val(savedVal);
            }
        });

        // Populate Unique ID Column dropdown
        var idSelect   = $('#gs-id-column');
        var savedIdVal = idSelect.val();
        var idOptions  = '';
        $.each(columns, function (i, col) {
            idOptions += '<option value="' + gsEsc(col) + '">' + gsEsc(col) + '</option>';
        });
        idSelect.html(idOptions);
        if (savedIdVal) {
            idSelect.val(savedIdVal);
        }

        // Rebuild description column checkboxes
        var descContainer = $('#gs-description-columns');
        var savedChecked  = [];
        descContainer.find('input[type=checkbox]:checked').each(function () {
            savedChecked.push($(this).val());
        });
        descContainer.empty();
        $.each(columns, function (i, col) {
            var checked = savedChecked.indexOf(col) !== -1 ? ' checked' : '';
            descContainer.append(
                '<div class="checkbox"><label>' +
                '<input type="checkbox" name="description_columns[]" value="' + gsEsc(col) + '"' + checked + '> ' +
                gsEsc(col) +
                '</label></div>'
            );
        });

        // Show mapping section
        $('#gs-mapping-section').show();
    };

    function gsEsc(str) {
        return $('<div>').text(str).html();
    }

    // Detect Columns button
    $(document).on('click', '#gs-detect-columns', function () {
        var btn            = $(this);
        var spreadsheetId  = $('#gs-spreadsheet-id').val().trim();
        var tabName        = $('#gs-sheet-tab').val().trim() || 'Sheet1';
        var statusEl       = $('#gs-detect-status');

        if (!spreadsheetId) {
            statusEl.removeClass('text-success text-danger').addClass('text-danger')
                    .text('Please enter a Spreadsheet ID or URL first.');
            return;
        }

        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Detecting...');
        statusEl.removeClass('text-success text-danger').text('');

        var payload = {spreadsheet_id: spreadsheetId, sheet_tab: tabName};
        payload[GS_CSRF_NAME] = GS_CSRF_HASH;

        $.post(GS_DETECT_URL, payload, function (resp) {
            btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns');
            if (resp.success && resp.columns && resp.columns.length > 0) {
                window.gsPopulateSelects(resp.columns);
                statusEl.addClass('text-success')
                        .text('Detected ' + resp.columns.length + ' column(s).');
            } else {
                var msg = (resp && resp.message) ? resp.message : 'No columns found or API error.';
                statusEl.addClass('text-danger').text(msg);
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns');
            statusEl.addClass('text-danger').text('Request failed. Check server error log.');
        });
    });

})(jQuery);
