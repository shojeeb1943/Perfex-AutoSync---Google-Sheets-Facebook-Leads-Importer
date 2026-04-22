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

    <div class="row" style="margin-bottom:16px;">
      <div class="col-md-12">
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 16px;background:#f8f9fa;border:1px solid #e3e6ea;border-radius:6px;font-size:13px;color:#555;">
          <span style="display:flex;align-items:center;gap:5px;">
            <i class="fa fa-tag" style="color:#4285f4;"></i>
            <strong style="color:#333;">Version</strong>&nbsp;<span class="label label-info" style="font-size:12px;vertical-align:middle;">v<?php echo defined('GS_LEAD_SYNC_VERSION') ? GS_LEAD_SYNC_VERSION : '1.2.0'; ?></span>
          </span>
          <span style="color:#ccc;">|</span>
          <span style="display:flex;align-items:center;gap:5px;">
            <i class="fa fa-info-circle" style="color:#34a853;"></i>
            Auto-import Facebook Ads leads from Google Sheets into Perfex CRM
          </span>
          <span style="color:#ccc;">|</span>
          <span style="display:flex;align-items:center;gap:5px;">
            <i class="fa fa-user-o" style="color:#888;"></i>
            <strong style="color:#333;">Author:</strong>&nbsp;Bytesis
          </span>
          <span style="color:#ccc;">|</span>
          <span style="display:flex;align-items:center;gap:5px;">
            <i class="fa fa-perfex fa-clock-o" style="color:#888;"></i>
            <strong style="color:#333;">Requires Perfex:</strong>&nbsp;2.3+
          </span>
          <?php if (!defined('GS_LEAD_SYNC_SERVICE_ACCOUNT_SET') && empty($service_account_set)): ?>
          <span style="margin-left:auto;display:flex;align-items:center;gap:5px;color:#e67e22;">
            <i class="fa fa-exclamation-triangle"></i>
            Setup not complete &mdash; configure your Google Service Account in <strong>Global Settings</strong>
          </span>
          <?php else: ?>
          <span style="margin-left:auto;display:flex;align-items:center;gap:5px;color:#27ae60;">
            <i class="fa fa-check-circle"></i>
            Google account connected &amp; ready
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

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
                  <td><?php echo htmlspecialchars($sheet['name']); ?></td>
                  <td><code><?php echo htmlspecialchars(substr($sheet['spreadsheet_id'], 0, 20)) . '...'; ?></code></td>
                  <td><?php echo htmlspecialchars($sheet['sheet_tab']); ?></td>
                  <td><?php echo htmlspecialchars($status_name); ?></td>
                  <td><?php echo htmlspecialchars($source_name); ?></td>
                  <td>
                    <?php if ($sheet['is_active']): ?>
                      <span class="label label-success">Active</span>
                    <?php else: ?>
                      <span class="label label-default">Paused</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn btn-xs btn-primary gs-sync-now"
                            data-id="<?php echo $sheet['id']; ?>"
                            data-name="<?php echo htmlspecialchars($sheet['name']); ?>">
                      <i class="fa fa-refresh"></i> Sync Now
                    </button>
                    <a href="<?php echo admin_url('gs_lead_sync/edit_sheet/' . $sheet['id']); ?>"
                       class="btn btn-xs btn-default">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <button class="btn btn-xs btn-danger gs-delete-sheet"
                            data-id="<?php echo $sheet['id']; ?>"
                            data-name="<?php echo htmlspecialchars($sheet['name']); ?>">
                      <i class="fa fa-trash"></i> Delete
                    </button>
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
        <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
        <div class="row">
          <div class="col-md-8">

            <div class="form-group">
              <label>Google Service Account JSON</label>
              <?php if ($service_account_set): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0faf4;border:1px solid #b2dfdb;border-radius:6px;margin-bottom:10px;">
                  <div style="width:38px;height:38px;border-radius:50%;background:#fff;border:2px solid #34a853;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa fa-google" style="color:#34a853;font-size:18px;"></i>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;color:#1a7340;font-size:13px;margin-bottom:2px;">
                      <i class="fa fa-check-circle" style="color:#34a853;margin-right:4px;"></i>Connected Google Service Account
                    </div>
                    <?php if ($service_account_email): ?>
                      <div style="color:#333;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <i class="fa fa-envelope-o" style="color:#888;margin-right:4px;"></i><?php echo htmlspecialchars($service_account_email); ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($service_account_project): ?>
                      <div style="color:#666;font-size:12px;margin-top:2px;">
                        <i class="fa fa-folder-o" style="color:#aaa;margin-right:4px;"></i>Project: <?php echo htmlspecialchars($service_account_project); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <small class="text-muted" style="display:block;margin-bottom:6px;">Paste a new JSON below to replace the connected account.</small>
              <?php else: ?>
                <p class="text-warning"><i class="fa fa-warning"></i> Not configured yet. Paste your Google Service Account JSON key below.</p>
              <?php endif; ?>
              <textarea name="service_account_json" class="form-control" rows="8"
                        placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'></textarea>
              <small class="text-muted">Leave blank to keep the existing key.</small>
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
              <label style="font-weight:normal;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="cron_enabled" value="1" style="width:16px;height:16px;flex-shrink:0;cursor:pointer;" <?php echo $cron_enabled == '1' ? 'checked' : ''; ?>>
                Enable automatic cron sync
              </label>
            </div>

            <div class="form-group">
              <label style="font-weight:normal;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="skip_test_leads" value="1" style="width:16px;height:16px;flex-shrink:0;cursor:pointer;" <?php echo $skip_test_leads != '0' ? 'checked' : ''; ?>>
                Skip Facebook test leads (rows containing <code>&lt;test lead:</code> markers)
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
          </div>
        </div>
      </form>
    </div><!-- /tab-global -->

  </div><!-- /tab-content -->

  </div><!-- /content -->
</div>

<script>
// Sync Now
$(document).on('click', '.gs-sync-now', function() {
    var btn  = $(this);
    var id   = btn.data('id');
    var name = btn.data('name');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner"></i> Syncing...');
    $.post('<?php echo admin_url("gs_lead_sync/sync_now/"); ?>' + id, {
        <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
    }, function(resp) {
        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Sync Now');
        if (resp.success) {
            var s = resp.stats;
            alert('Sync complete for "' + name + '":\nImported: ' + s.rows_imported + '\nSkipped: ' + s.rows_skipped + '\nFailed: ' + s.rows_failed);
        } else {
            alert('Sync failed: ' + (resp.message || 'Unknown error'));
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Sync Now');
        alert('Request failed. Check server logs.');
    });
});

// Delete sheet
$(document).on('click', '.gs-delete-sheet', function() {
    var id   = $(this).data('id');
    var name = $(this).data('name');
    if (!confirm('Delete sheet configuration "' + name + '"? This cannot be undone.')) return;
    $.post('<?php echo admin_url("gs_lead_sync/delete_sheet/"); ?>' + id, {
        <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
    }, function() { location.reload(); });
});
</script>

<?php init_tail(); ?>
