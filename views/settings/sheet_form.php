<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <h4 class="font-bold"><?php echo $title; ?></h4>
        <hr class="hr-panel-heading" />
      </div>
    </div>

  <?php
  $s_name      = isset($sheet['name'])            ? $sheet['name']            : '';
  $s_sid       = isset($sheet['spreadsheet_id'])  ? $sheet['spreadsheet_id']  : '';
  $s_tab       = isset($sheet['sheet_tab'])        ? $sheet['sheet_tab']        : 'Sheet1';
  $s_id_col    = isset($sheet['id_column'])        ? $sheet['id_column']        : '';
  $s_status_id = isset($sheet['lead_status_id'])   ? $sheet['lead_status_id']   : '';
  $s_source_id = isset($sheet['lead_source_id'])   ? $sheet['lead_source_id']   : '';
  $s_assignee  = isset($sheet['default_assignee']) ? $sheet['default_assignee'] : '';
  $s_is_active = isset($sheet['is_active'])        ? $sheet['is_active']        : 1;
  $s_col_map   = isset($sheet['column_mapping'])   ? $sheet['column_mapping']   : array();
  ?>

  <form method="POST" action="<?php echo admin_url('gs_lead_sync/save_sheet'); ?>" id="sheet-config-form">
    <?php echo form_hidden($csrf_name, $csrf_hash); ?>
    <?php if (!empty($sheet['id'])): ?>
      <input type="hidden" name="id" value="<?php echo (int)$sheet['id']; ?>">
    <?php endif; ?>

    <div class="row">
      <div class="col-md-8">

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Sheet Details</h4></div>
          <div class="panel-body">

            <div class="form-group">
              <label>Configuration Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                     value="<?php echo htmlspecialchars($s_name, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
              <label>Google Spreadsheet ID or URL <span class="text-danger">*</span></label>
              <input type="text" name="spreadsheet_id" id="gs-spreadsheet-id" class="form-control" required
                     placeholder="Paste full URL or just the spreadsheet ID"
                     value="<?php echo htmlspecialchars($s_sid, ENT_QUOTES, 'UTF-8'); ?>">
              <small class="text-muted">The ID is the long string between /d/ and /edit in the URL.</small>
            </div>

            <div class="form-group">
              <label>Sheet Tab Name</label>
              <input type="text" name="sheet_tab" id="gs-sheet-tab" class="form-control"
                     placeholder="Sheet1"
                     value="<?php echo htmlspecialchars($s_tab, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
              <button type="button" id="gs-detect-columns" class="btn btn-info">
                <i class="fa fa-search"></i> Detect Columns from Sheet
              </button>
              <span id="gs-detect-status" class="mleft10"></span>
            </div>

            <div id="gs-detected-columns-info" style="display:none;">
              <div class="alert alert-success" style="margin-bottom:0;">
                <strong><i class="fa fa-check"></i> Columns detected:</strong>
                <span id="gs-columns-list"></span>
              </div>
            </div>

          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Lead Assignment</h4></div>
          <div class="panel-body">

            <div class="form-group">
              <label>Lead Status for New Leads</label>
              <select name="lead_status_id" class="form-control">
                <option value="">— Select —</option>
                <?php foreach ($lead_statuses as $ls): ?>
                <option value="<?php echo $ls['id']; ?>"
                  <?php echo ($s_status_id == $ls['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($ls['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Lead Source for New Leads</label>
              <select name="lead_source_id" class="form-control">
                <option value="">— Select —</option>
                <?php foreach ($lead_sources as $ls): ?>
                <option value="<?php echo $ls['id']; ?>"
                  <?php echo ($s_source_id == $ls['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($ls['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Default Assignee</label>
              <select name="default_assignee" class="form-control">
                <option value="">— Unassigned —</option>
                <?php foreach ((isset($staff_list) ? $staff_list : array()) as $st):
                    $st_name = trim((isset($st['firstname']) ? $st['firstname'] : '') . ' ' . (isset($st['lastname']) ? $st['lastname'] : ''));
                    if ($st_name === '') { $st_name = isset($st['email']) ? $st['email'] : ('Staff #' . $st['staffid']); }
                ?>
                <option value="<?php echo (int)$st['staffid']; ?>"
                  <?php echo ($s_assignee !== '' && (int)$s_assignee === (int)$st['staffid']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($st_name, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="gs-checkbox-label">
                <input type="checkbox" name="is_active" value="1"
                  <?php echo $s_is_active ? 'checked' : ''; ?>>
                Active (include in sync runs)
              </label>
            </div>

          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Column Mapping</h4></div>
          <div class="panel-body">
            <p class="text-muted">
              Click <strong>"Detect Columns"</strong> above to populate dropdowns automatically,
              or type the exact column header name from your Google Sheet. Leave blank to skip.
            </p>
            <table class="table table-condensed">
              <thead>
                <tr>
                  <th style="width:35%">CRM Field</th>
                  <th>Sheet Column Header</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($crm_fields as $crm_key => $crm_label): ?>
                <tr>
                  <td>
                    <?php echo htmlspecialchars($crm_label, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($crm_key === 'name'): ?><span class="text-danger">*</span><?php endif; ?>
                  </td>
                  <td>
                    <select name="column_mapping[<?php echo $crm_key; ?>]"
                            class="form-control gs-col-select"
                            data-crm-key="<?php echo $crm_key; ?>"
                            data-saved-value="<?php echo htmlspecialchars(isset($s_col_map[$crm_key]) ? $s_col_map[$crm_key] : '', ENT_QUOTES, 'UTF-8'); ?>">
                      <option value="">— Skip —</option>
                      <?php if (isset($s_col_map[$crm_key]) && $s_col_map[$crm_key] !== ''): ?>
                      <option value="<?php echo htmlspecialchars($s_col_map[$crm_key], ENT_QUOTES, 'UTF-8'); ?>" selected>
                        <?php echo htmlspecialchars($s_col_map[$crm_key], ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                      <?php endif; ?>
                    </select>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Row Skip Ranges</h4></div>
          <div class="panel-body">
            <p class="text-muted">
              Specify sheet row numbers to skip during sync (e.g. rows already imported).
              Row 1 is always the header. First data row is row 2.
            </p>
            <table class="table table-condensed" id="gs-skip-ranges-table">
              <thead>
                <tr>
                  <th style="width:40%">From Row <span class="text-danger">*</span></th>
                  <th style="width:40%">To Row <span class="text-danger">*</span></th>
                  <th style="width:20%"></th>
                </tr>
              </thead>
              <tbody id="gs-skip-ranges-body">
                <?php
                $s_skip_rows = array();
                if (!empty($sheet['skip_rows'])) {
                    $decoded = json_decode($sheet['skip_rows'], true);
                    if (is_array($decoded)) { $s_skip_rows = $decoded; }
                }
                foreach ($s_skip_rows as $i => $range):
                ?>
                <tr class="gs-skip-range-row">
                  <td>
                    <input type="number" name="skip_rows[<?php echo $i; ?>][from]"
                           class="form-control" min="2" placeholder="e.g. 2"
                           value="<?php echo (int)$range['from']; ?>">
                  </td>
                  <td>
                    <input type="number" name="skip_rows[<?php echo $i; ?>][to]"
                           class="form-control" min="2" placeholder="e.g. 100"
                           value="<?php echo (int)$range['to']; ?>">
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm gs-remove-range">
                      <i class="fa fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <button type="button" id="gs-add-range" class="btn btn-default btn-sm">
              <i class="fa fa-plus"></i> Add Range
            </button>
          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Unique Row ID Column</h4></div>
          <div class="panel-body">
            <div class="form-group">
              <label>ID Column <span class="text-danger">*</span></label>
              <select name="id_column" id="gs-id-column" class="form-control" required>
                <option value="">— Select —</option>
                <?php if ($s_id_col !== ''): ?>
                <option value="<?php echo htmlspecialchars($s_id_col, ENT_QUOTES, 'UTF-8'); ?>" selected>
                  <?php echo htmlspecialchars($s_id_col, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endif; ?>
              </select>
              <small class="text-muted">
                The column whose value uniquely identifies each row (prevents duplicate imports).
                For Facebook leads sheets this is typically <strong>id</strong>.
                Click "Detect Columns" above to see available options.
              </small>
            </div>
          </div>
        </div>

        <div class="mtop15">
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i> Save Configuration
          </button>
          <a href="<?php echo admin_url('gs_lead_sync'); ?>" class="btn btn-default mleft5">Cancel</a>
        </div>

      </div>
    </div>
  </form>

  </div><!-- /content -->
</div>

<?php init_tail(); ?>

<script>
var GS_CSRF_NAME  = '<?php echo $csrf_name; ?>';
var GS_CSRF_HASH  = '<?php echo $csrf_hash; ?>';
var GS_DETECT_URL = '<?php echo admin_url("gs_lead_sync/detect_columns"); ?>';

var gsSkipRangeIndex = <?php echo max(count($s_skip_rows), 0); ?>;

$('#gs-add-range').on('click', function () {
    var i = gsSkipRangeIndex++;
    var row = '<tr class="gs-skip-range-row">' +
        '<td><input type="number" name="skip_rows[' + i + '][from]" class="form-control" min="2" placeholder="e.g. 2"></td>' +
        '<td><input type="number" name="skip_rows[' + i + '][to]" class="form-control" min="2" placeholder="e.g. 100"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm gs-remove-range"><i class="fa fa-trash"></i></button></td>' +
        '</tr>';
    $('#gs-skip-ranges-body').append(row);
});

$(document).on('click', '.gs-remove-range', function () {
    $(this).closest('tr.gs-skip-range-row').remove();
});

$('#gs-detect-columns').on('click', function () {
    var btn      = $(this);
    var statusEl = $('#gs-detect-status');
    var sid      = $('#gs-spreadsheet-id').val().trim();
    var tab      = $('#gs-sheet-tab').val().trim() || 'Sheet1';

    if (!sid) {
        statusEl.html('<span class="text-danger">Enter Spreadsheet ID first.</span>');
        return;
    }

    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Detecting...');
    statusEl.html('');

    var payload = {};
    payload[GS_CSRF_NAME] = GS_CSRF_HASH;
    payload.spreadsheet_id = sid;
    payload.sheet_tab = tab;

    $.ajax({
        url: GS_DETECT_URL,
        method: 'POST',
        data: payload,
        dataType: 'json'
    }).done(function (resp) {
        if (resp && resp.csrf_hash) { GS_CSRF_HASH = resp.csrf_hash; }
        btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns from Sheet');

        if (resp && resp.success && resp.columns && resp.columns.length) {
            var cols = resp.columns;
            statusEl.html('<span class="text-success"><i class="fa fa-check"></i> ' + cols.length + ' columns found!</span>');

            // Show detected columns info
            $('#gs-columns-list').text(cols.join(', '));
            $('#gs-detected-columns-info').slideDown();

            // Populate all column mapping dropdowns
            $('.gs-col-select').each(function () {
                var sel = $(this);
                var saved = sel.data('saved-value') || '';
                sel.empty().append('<option value="">— Skip —</option>');
                for (var i = 0; i < cols.length; i++) {
                    var isSelected = (saved === cols[i]) ? ' selected' : '';
                    sel.append('<option value="' + cols[i] + '"' + isSelected + '>' + cols[i] + '</option>');
                }
            });

            // Populate ID column dropdown
            var idSel = $('#gs-id-column');
            var idSaved = idSel.find('option:selected').val() || '';
            idSel.empty().append('<option value="">— Select —</option>');
            for (var i = 0; i < cols.length; i++) {
                var isSelected = (idSaved === cols[i]) ? ' selected' : '';
                idSel.append('<option value="' + cols[i] + '"' + isSelected + '>' + cols[i] + '</option>');
            }
        } else {
            var msg = (resp && resp.message) ? resp.message : 'No columns found.';
            statusEl.html('<span class="text-danger"><i class="fa fa-times"></i> ' + msg + '</span>');
        }
    }).fail(function (xhr) {
        btn.prop('disabled', false).html('<i class="fa fa-search"></i> Detect Columns from Sheet');
        if (xhr.status === 403) {
            statusEl.html('<span class="text-danger">Session expired — please reload.</span>');
            return;
        }
        var msg = 'Request failed (HTTP ' + xhr.status + ').';
        try {
            var parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.message) { msg = parsed.message; }
        } catch (e) {}
        statusEl.html('<span class="text-danger">' + msg + '</span>');
    });
});
</script>
