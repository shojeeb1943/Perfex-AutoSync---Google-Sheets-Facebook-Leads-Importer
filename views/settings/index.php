<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <?php $this->load->view('includes/header_wrapper_start'); ?>

  <div class="row">
    <div class="col-md-12">
      <h4 class="font-bold"><?php echo $title; ?></h4>
      <hr class="hr-panel-heading" />
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
                <p class="text-success"><i class="fa fa-check"></i> Service account is configured. Paste a new JSON below to replace it.</p>
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
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="cron_enabled" value="1" <?php echo $cron_enabled == '1' ? 'checked' : ''; ?>>
                  Enable automatic cron sync
                </label>
              </div>
            </div>

            <div class="form-group">
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="skip_test_leads" value="1" <?php echo $skip_test_leads != '0' ? 'checked' : ''; ?>>
                  Skip Facebook test leads (rows containing <code>&lt;test lead:</code> markers)
                </label>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
          </div>
        </div>
      </form>
    </div><!-- /tab-global -->

  </div><!-- /tab-content -->

  <?php $this->load->view('includes/footer_wrapper'); ?>
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
