<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <!-- Page Header -->
    <div class="row">
      <div class="col-md-12">
        <div class="flex-row" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
          <div>
            <h4 class="font-bold" style="margin:0;"><?php echo $title; ?></h4>
            <small class="text-muted">Track every sync run — imports, skips, failures, and timing.</small>
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            <a href="<?php echo admin_url('gs_lead_sync'); ?>" class="btn btn-default btn-sm">
              <i class="fa fa-arrow-left"></i> Back to Settings
            </a>
            <?php if (!empty($logs)): ?>
            <form method="POST" action="<?php echo admin_url('gs_lead_sync/clear_logs'); ?>" class="display-inline" style="margin:0;">
              <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('This will permanently delete all sync logs. Continue?')">
                <i class="fa fa-trash"></i> Clear All Logs
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <hr class="hr-panel-heading" />
      </div>
    </div>

    <?php if (empty($logs)): ?>

    <!-- Empty State -->
    <div class="row">
      <div class="col-md-6 col-md-offset-3">
        <div style="text-align:center; padding:60px 20px;">
          <div style="width:72px; height:72px; border-radius:50%; background:#f0f4ff; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px;">
            <i class="fa fa-history" style="font-size:32px; color:#6c8ebf;"></i>
          </div>
          <h4 style="font-weight:600; color:#333; margin-bottom:8px;">No sync runs yet</h4>
          <p class="text-muted" style="margin-bottom:24px;">Logs will appear here after your first manual or scheduled sync runs.</p>
          <a href="<?php echo admin_url('gs_lead_sync'); ?>" class="btn btn-primary">
            <i class="fa fa-cog"></i> Go to Sheet Configurations
          </a>
        </div>
      </div>
    </div>

    <?php else:

    // Compute summary stats
    $total_fetched  = array_sum(array_column($logs, 'rows_fetched'));
    $total_imported = array_sum(array_column($logs, 'rows_imported'));
    $total_skipped  = array_sum(array_column($logs, 'rows_skipped'));
    $total_failed   = array_sum(array_column($logs, 'rows_failed'));
    ?>

    <!-- Summary Stats -->
    <div class="row mbottom20">
      <div class="col-xs-6 col-sm-3">
        <div style="background:#fff; border:1px solid #e0e6ed; border-radius:6px; padding:14px 18px; text-align:center;">
          <div style="font-size:22px; font-weight:700; color:#337ab7;"><?php echo $total_fetched; ?></div>
          <div style="font-size:12px; color:#8a94a6; margin-top:2px;"><i class="fa fa-download"></i> Total Fetched</div>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div style="background:#fff; border:1px solid #e0e6ed; border-radius:6px; padding:14px 18px; text-align:center;">
          <div style="font-size:22px; font-weight:700; color:#5cb85c;"><?php echo $total_imported; ?></div>
          <div style="font-size:12px; color:#8a94a6; margin-top:2px;"><i class="fa fa-check"></i> Total Imported</div>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div style="background:#fff; border:1px solid #e0e6ed; border-radius:6px; padding:14px 18px; text-align:center;">
          <div style="font-size:22px; font-weight:700; color:#f0ad4e;"><?php echo $total_skipped; ?></div>
          <div style="font-size:12px; color:#8a94a6; margin-top:2px;"><i class="fa fa-forward"></i> Total Skipped</div>
        </div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div style="background:#fff; border:1px solid #e0e6ed; border-radius:6px; padding:14px 18px; text-align:center;">
          <div style="font-size:22px; font-weight:700; color:<?php echo $total_failed > 0 ? '#d9534f' : '#8a94a6'; ?>;"><?php echo $total_failed; ?></div>
          <div style="font-size:12px; color:#8a94a6; margin-top:2px;"><i class="fa fa-times-circle"></i> Total Failed</div>
        </div>
      </div>
    </div>

    <!-- Log Table -->
    <div class="row">
      <div class="col-md-12">
        <div style="background:#fff; border:1px solid #e0e6ed; border-radius:6px; overflow:hidden;">
          <div class="table-responsive" style="margin:0;">
            <table class="table table-hover" style="margin:0;">
              <thead style="background:#f8f9fc;">
                <tr>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">#</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Sheet</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Trigger</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Fetched</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Imported</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Skipped</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Failed</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Started</th>
                  <th style="border-top:none; padding:12px 16px; font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">Duration</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $i => $log):
                  $started  = strtotime($log['started_at']);
                  $finished = strtotime($log['finished_at']);
                  $duration_sec = ($started && $finished) ? ($finished - $started) : null;
                  $duration = $duration_sec !== null
                    ? ($duration_sec < 60 ? $duration_sec . 's' : round($duration_sec / 60, 1) . 'm')
                    : '—';
                  $errors = json_decode($log['error_details'] ?? '[]', true) ?: [];
                  $has_errors = !empty($errors) || $log['rows_failed'] > 0;
                  $row_style = $has_errors ? 'border-left:3px solid #d9534f;' : '';
                ?>
                <tr style="<?php echo $row_style; ?>">
                  <td style="padding:12px 16px; vertical-align:middle; color:#9ca3af; font-size:12px;"><?php echo $log['id']; ?></td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <strong style="color:#1f2937;"><?php echo htmlspecialchars($log['sheet_name'] ?? '(deleted)'); ?></strong>
                  </td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <?php if ($log['triggered_by'] === 'cron'): ?>
                      <span style="background:#e5e7eb; color:#374151; font-size:11px; padding:3px 8px; border-radius:20px; font-weight:500;">
                        <i class="fa fa-clock-o"></i> Cron
                      </span>
                    <?php else: ?>
                      <span style="background:#dbeafe; color:#1d4ed8; font-size:11px; padding:3px 8px; border-radius:20px; font-weight:500;">
                        <i class="fa fa-hand-o-right"></i> Manual
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px 16px; vertical-align:middle; color:#374151;"><?php echo $log['rows_fetched']; ?></td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <?php if ($log['rows_imported'] > 0): ?>
                      <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 8px; border-radius:12px;">
                        <?php echo $log['rows_imported']; ?>
                      </span>
                    <?php else: ?>
                      <span style="color:#9ca3af;">0</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px 16px; vertical-align:middle; color:#6b7280;"><?php echo $log['rows_skipped']; ?></td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <?php if ($log['rows_failed'] > 0): ?>
                      <span style="background:#fee2e2; color:#991b1b; font-size:12px; font-weight:600; padding:2px 8px; border-radius:12px;">
                        <?php echo $log['rows_failed']; ?>
                      </span>
                    <?php else: ?>
                      <span style="color:#9ca3af;">0</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <span style="color:#374151; font-size:13px;"><?php echo date('M j, Y', $started); ?></span><br>
                    <small style="color:#9ca3af;"><?php echo date('g:i A', $started); ?></small>
                  </td>
                  <td style="padding:12px 16px; vertical-align:middle;">
                    <?php if ($duration_sec !== null): ?>
                      <span style="background:#f3f4f6; color:#374151; font-size:12px; padding:2px 8px; border-radius:12px;">
                        <i class="fa fa-bolt" style="color:#f59e0b;"></i> <?php echo $duration; ?>
                      </span>
                    <?php else: ?>
                      <span style="color:#9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if (!empty($errors)): ?>
                <tr>
                  <td colspan="9" style="padding:0 16px 12px 32px; background:#fff8f8; border-top:none;">
                    <div style="border-left:3px solid #f87171; padding:10px 14px; border-radius:0 4px 4px 0; background:#fff1f2;">
                      <strong style="color:#b91c1c; font-size:12px;"><i class="fa fa-exclamation-triangle"></i> Errors in this run:</strong>
                      <ul style="margin:6px 0 0 0; padding-left:16px;">
                        <?php foreach ($errors as $err): ?>
                        <li style="font-size:12px; color:#7f1d1d; margin-bottom:2px;"><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <?php
    $total_pages = ceil($total_logs / $limit);
    if ($total_pages > 1):
    ?>
    <div class="row mtop15">
      <div class="col-md-12" style="display:flex; align-items:center; justify-content:space-between;">
        <small class="text-muted">
          Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
          &mdash; <?php echo $total_logs; ?> total runs
        </small>
        <ul class="pagination" style="margin:0;">
          <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a href="<?php echo admin_url('gs_lead_sync/sync_log?page=' . max(1, $page - 1)); ?>">&laquo;</a>
          </li>
          <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
          <li class="<?php echo $p == $page ? 'active' : ''; ?>">
            <a href="<?php echo admin_url('gs_lead_sync/sync_log?page=' . $p); ?>"><?php echo $p; ?></a>
          </li>
          <?php endfor; ?>
          <li class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a href="<?php echo admin_url('gs_lead_sync/sync_log?page=' . min($total_pages, $page + 1)); ?>">&raquo;</a>
          </li>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /content -->
</div>
<?php init_tail(); ?>
