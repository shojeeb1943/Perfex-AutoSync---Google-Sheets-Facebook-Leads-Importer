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

    <div class="row">
      <div class="col-md-12">
        <div class="gs-info-banner">
          <span class="gs-item">
            <i class="fa fa-tag"></i>
            <strong>Version</strong>&nbsp;<span class="label label-info">v<?php echo GS_LEAD_SYNC_VERSION; ?></span>
          </span>
          <span class="gs-sep">|</span>
          <span class="gs-item">
            <i class="fa fa-info-circle"></i>
            Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
          </span>
          <span class="gs-sep">|</span>
          <span class="gs-item">
            <i class="fa fa-user-o"></i>
            <strong>Author:</strong>&nbsp;Bytesis
          </span>
          <span class="gs-sep">|</span>
          <span class="gs-item">
            <i class="fa fa-clock-o"></i>
            <strong>Requires Perfex:</strong>&nbsp;2.3+
          </span>
          <?php if (empty($service_account_set)): ?>
          <span class="gs-item gs-status-warning">
            <i class="fa fa-exclamation-triangle"></i>
            Setup not complete &mdash; configure your Google Service Account in <strong>Global Settings</strong>
          </span>
          <?php else: ?>
          <span class="gs-item gs-status-ok">
            <i class="fa fa-check-circle"></i>
            Google account connected &amp; ready
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

  <div class="row">
    <div class="col-md-12">
      <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active">
          <a href="#tab-sheets" data-toggle="tab">Sheet Configurations</a>
        </li>
        <li role="presentation">
          <a href="#tab-global" data-toggle="tab">Global Settings</a>
        </li>
        <li role="presentation">
          <a href="<?php echo admin_url('gs_lead_sync/sync_log'); ?>">Sync Log</a>
        </li>
      </ul>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
  <div class="tab-content mtop15">

    <!-- SHEET CONFIGS TAB -->
    <div role="tabpanel" class="tab-pane active" id="tab-sheets">
      <div class="row">
        <div class="col-md-12">
          <a href="<?php echo admin_url('gs_lead_sync/add_sheet'); ?>" class="btn btn-info pull-right">
            <i class="fa fa-plus"></i> Add Sheet
          </a>
        </div>
      </div>
      <div class="row mtop15">
        <div class="col-md-12">
          <?php if (empty($sheets)): ?>
            <p class="text-muted">No sheet configurations yet. Click "Add Sheet" to get started.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Spreadsheet ID</th>
                  <th>Tab</th>
                  <th>Status</th>
                  <th>Source</th>
                  <th>Active</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sheets as $sheet): ?>
                <?php
                  $status_name = '';
                  foreach ($lead_statuses as $s) {
                      if ($s['id'] == $sheet['lead_status_id']) { $status_name = $s['name']; break; }
                  }
                  $source_name = '';
                  foreach ($lead_sources as $s) {
                      if ($s['id'] == $sheet['lead_source_id']) { $source_name = $s['name']; break; }
                  }
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($sheet['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><code><?php echo htmlspecialchars(substr($sheet['spreadsheet_id'], 0, 20), ENT_QUOTES, 'UTF-8') . '...'; ?></code></td>
                  <td><?php echo htmlspecialchars($sheet['sheet_tab'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($status_name, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($source_name, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ($sheet['is_active']): ?>
                      <span class="label label-success">Active</span>
                    <?php else: ?>
                      <span class="label label-default">Paused</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-xs btn-primary gs-sync-now"
                            data-id="<?php echo (int)$sheet['id']; ?>"
                            data-name="<?php echo htmlspecialchars($sheet['name'], ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="fa fa-refresh"></i> Sync Now
                    </button>
                    <a href="<?php echo admin_url('gs_lead_sync/edit_sheet/' . (int)$sheet['id']); ?>"
                       class="btn btn-xs btn-default">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <form method="POST" action="<?php echo admin_url('gs_lead_sync/delete_sheet/' . (int)$sheet['id']); ?>" class="display-inline gs-delete-form" style="display:inline;">
                      <?php echo form_hidden($csrf_name, $csrf_hash); ?>
                      <button type="submit" class="btn btn-xs btn-danger gs-delete-sheet"
                              data-name="<?php echo htmlspecialchars($sheet['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-trash"></i> Delete
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /tab-sheets -->

    <!-- GLOBAL SETTINGS TAB -->
    <div role="tabpanel" class="tab-pane" id="tab-global">
      <form method="POST" action="<?php echo admin_url('gs_lead_sync/save_settings'); ?>">
        <?php echo form_hidden($csrf_name, $csrf_hash); ?>
        <div class="row">
          <div class="col-md-8">

            <div class="form-group">
              <label>Google Service Account JSON</label>
              <?php if ($service_account_set): ?>
                <div class="gs-sa-card">
                  <div class="gs-sa-avatar"><i class="fa fa-google"></i></div>
                  <div class="gs-sa-body">
                    <div class="gs-sa-title">
                      <i class="fa fa-check-circle"></i>Connected Google Service Account
                    </div>
                    <?php if ($service_account_email): ?>
                      <div class="gs-sa-email">
                        <i class="fa fa-envelope-o"></i><?php echo htmlspecialchars($service_account_email, ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($service_account_project): ?>
                      <div class="gs-sa-project">
                        <i class="fa fa-folder-o"></i>Project: <?php echo htmlspecialchars($service_account_project, ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <small class="text-muted gs-sa-note">Paste a new JSON below to replace the connected account.</small>
              <?php else: ?>
                <p class="text-warning"><i class="fa fa-warning"></i> Not configured yet. Paste your Google Service Account JSON key below.</p>
              <?php endif; ?>
              <textarea name="service_account_json" id="gs-sa-json" class="form-control" rows="8"
                        placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'></textarea>
              <small class="text-muted">Leave blank to keep the existing key.</small>
              <div class="mtop10">
                <button type="button" id="gs-test-connection" class="btn btn-default btn-sm">
                  <i class="fa fa-plug"></i> Test Google Connection
                </button>
                <span id="gs-test-status" class="mleft5"></span>
              </div>
            </div>

            <div class="form-group">
              <label>Cron Sync Interval</label>
              <select name="cron_interval" class="form-control">
                <?php
                $intervals = ['15min' => 'Every 15 minutes', '30min' => 'Every 30 minutes', '1hr' => 'Every 1 hour', '6hr' => 'Every 6 hours', 'daily' => 'Daily'];
                foreach ($intervals as $val => $label):
                ?>
                <option value="<?php echo $val; ?>" <?php echo $cron_interval == $val ? 'selected' : ''; ?>>
                  <?php echo $label; ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="gs-checkbox-label">
                <input type="checkbox" name="cron_enabled" value="1" <?php echo $cron_enabled == '1' ? 'checked' : ''; ?>>
                Enable automatic cron sync
              </label>
            </div>

            <div class="form-group">
              <label class="gs-checkbox-label">
                <input type="checkbox" name="skip_test_leads" value="1" <?php echo $skip_test_leads != '0' ? 'checked' : ''; ?>>
                Skip Facebook test leads (rows containing <code>&lt;test lead:</code> markers)
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
          </div>
        </div>
      </form>
    </div><!-- /tab-global -->

  </div><!-- /tab-content -->
    </div><!-- /col -->
  </div><!-- /row -->

  </div><!-- /content -->
</div>

<script>
// CSRF token kept in a variable so it can be refreshed from AJAX responses
// (guards against CSRF regeneration by background Perfex requests).
var GS_CSRF_NAME = '<?php echo $csrf_name; ?>';
var GS_CSRF_HASH = '<?php echo $csrf_hash; ?>';
var GS_SYNC_URL  = '<?php echo admin_url("gs_lead_sync/sync_now/"); ?>';
var GS_TEST_URL  = '<?php echo admin_url("gs_lead_sync/test_connection"); ?>';

function gsCSRF() {
    var d = {};
    d[GS_CSRF_NAME] = GS_CSRF_HASH;
    return d;
}

$(document).on('click', '.gs-sync-now', function () {
    var btn  = $(this);
    var id   = btn.data('id');
    var name = btn.data('name');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Syncing...');

    $.ajax({
        url: GS_SYNC_URL + id,
        method: 'POST',
        data: gsCSRF(),
        dataType: 'json'
    }).done(function (resp) {
        if (resp && resp.csrf_hash) { GS_CSRF_HASH = resp.csrf_hash; }
        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Sync Now');
        if (resp && resp.success) {
            var s = resp.stats;
            var msg = 'Sync complete for "' + name + '":\n'
                    + 'Imported: ' + s.rows_imported + '\n'
                    + 'Skipped: '  + s.rows_skipped  + '\n'
                    + 'Failed: '   + s.rows_failed;
            if (s.error_details && s.error_details.length) {
                msg += '\n\nErrors:\n' + s.error_details.slice(0, 5).join('\n');
            }
            alert(msg);
        } else {
            alert('Sync failed: ' + ((resp && resp.message) || 'Unknown error'));
        }
    }).fail(function (xhr) {
        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Sync Now');
        if (xhr.status === 403 || xhr.status === 419) {
            if (typeof window.gsHandleSessionExpired === 'function') {
                window.gsHandleSessionExpired(null);
            } else {
                alert('Session expired — reloading.');
                setTimeout(function () { location.reload(); }, 800);
            }
            return;
        }
        var msg = 'Request failed (HTTP ' + xhr.status + ').';
        try {
            var parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.message) { msg = parsed.message; }
        } catch (e) { /* keep generic */ }
        alert(msg);
    });
});

// Delete sheet — intercept the form submit for a confirm() prompt; the form
// itself carries the CSRF token and POSTs to the controller.
$(document).on('submit', '.gs-delete-form', function (e) {
    var name = $(this).find('.gs-delete-sheet').data('name');
    if (!confirm('Delete sheet configuration "' + name + '"? This cannot be undone.')) {
        e.preventDefault();
    }
});

// Test Google Connection — exercises the OAuth round-trip server-side.
$(document).on('click', '#gs-test-connection', function () {
    var btn      = $(this);
    var statusEl = $('#gs-test-status');
    var jsonNow  = $('#gs-sa-json').val();

    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Testing...');
    statusEl.removeClass('text-success text-danger').text('');

    var payload = gsCSRF();
    if (jsonNow && jsonNow.length > 10) {
        payload.service_account_json = jsonNow;
    }

    $.ajax({
        url: GS_TEST_URL,
        method: 'POST',
        data: payload,
        dataType: 'json'
    }).done(function (resp) {
        if (resp && resp.csrf_hash) { GS_CSRF_HASH = resp.csrf_hash; }
        btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Google Connection');
        if (resp && resp.success) {
            var text = resp.message;
            if (resp.details && resp.details.client_email) {
                text += ' (' + resp.details.client_email + ')';
            }
            statusEl.addClass('text-success').text(text);
        } else {
            statusEl.addClass('text-danger').text((resp && resp.message) || 'Test failed.');
        }
    }).fail(function (xhr) {
        btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Google Connection');
        if (xhr.status === 403 || xhr.status === 419) {
            window.gsHandleSessionExpired ? window.gsHandleSessionExpired(statusEl) : location.reload();
            return;
        }
        var msg = 'Request failed (HTTP ' + xhr.status + ').';
        try {
            var parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.message) { msg = parsed.message; }
        } catch (e) { /* keep generic */ }
        statusEl.addClass('text-danger').text(msg);
    });
});
</script>

<?php init_tail(); ?>
