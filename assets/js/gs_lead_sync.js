/**
 * gs_lead_sync — frontend JS for sheet form and Detect Columns AJAX
 */

(function ($) {
    'use strict';

    // Called on page load in edit mode (with pre-detected columns) and after AJAX detection
    window.gsPopulateSelects = function (columns) {
        if (!columns || columns.length === 0) return;

        $('.gs-col-select').each(function () {
            var select   = $(this);
            var savedVal = select.val();
            var options  = '<option value="">— Skip —</option>';
            $.each(columns, function (i, col) {
                options += '<option value="' + gsEsc(col) + '">' + gsEsc(col) + '</option>';
            });
            select.html(options);
            if (savedVal) {
                select.val(savedVal);
            }
        });

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

        $('#gs-mapping-section').show();
    };

    function gsEsc(str) {
        return $('<div>').text(str).html();
    }

    // Shared session-expired handler. Called when the server responds with
    // 403 or the body is HTML (CSRF mismatch returns the CI error page).
    window.gsHandleSessionExpired = function (statusEl) {
        var msg = 'Session expired — reloading in a moment…';
        if (statusEl && statusEl.length) {
            statusEl.removeClass('text-success').addClass('text-danger').text(msg);
        } else {
            alert(msg);
        }
        setTimeout(function () { location.reload(); }, 1500);
    };

    $(document).on('click', '#gs-detect-columns', function () {
        var btn           = $(this);
        var spreadsheetId = $('#gs-spreadsheet-id').val().trim();
        var tabName       = $('#gs-sheet-tab').val().trim() || 'Sheet1';
        var statusEl      = $('#gs-detect-status');

        if (!spreadsheetId) {
            statusEl.removeClass('text-success text-danger').addClass('text-danger')
                    .text('Please enter a Spreadsheet ID or URL first.');
            return;
        }

        btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Detecting...');
        statusEl.removeClass('text-success text-danger').text('');

        var payload = {spreadsheet_id: spreadsheetId, sheet_tab: tabName};
        if (typeof GS_CSRF_NAME !== 'undefined' && typeof GS_CSRF_HASH !== 'undefined') {
            payload[GS_CSRF_NAME] = GS_CSRF_HASH;
        }

        $.ajax({
            url: GS_DETECT_URL,
            method: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.csrf_hash) { GS_CSRF_HASH = resp.csrf_hash; }
            btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns');
            if (resp && resp.success && resp.columns && resp.columns.length > 0) {
                window.gsPopulateSelects(resp.columns);
                statusEl.removeClass('text-success text-danger').addClass('text-success')
                        .text('Detected ' + resp.columns.length + ' column(s). Map them below, then save.');
            } else {
                var msg = (resp && resp.message) ? resp.message : 'No columns found or API error.';
                statusEl.removeClass('text-success text-danger').addClass('text-danger').text(msg);
            }
        }).fail(function (xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns');
            if (xhr.status === 403 || xhr.status === 419) {
                window.gsHandleSessionExpired(statusEl);
                return;
            }
            // CI 500 or HTML response — try to extract message, else advise log check.
            var msg = 'Request failed (HTTP ' + xhr.status + '). Check server error log.';
            try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.message) { msg = parsed.message; }
            } catch (e) { /* not JSON — keep generic */ }
            statusEl.removeClass('text-success text-danger').addClass('text-danger').text(msg);
        });
    });

})(jQuery);
