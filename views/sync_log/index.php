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

  <div class="row mbottom15">
    <div class="col-md-12">
      <a href="<?php echo admin_url('gs_lead_sync'); ?>" class="btn btn-default btn-xs">
        <i class="fa fa-arrow-left"></i> Back to Settings
      </a>
      <?php if (!empty($logs)): ?>
      <form method="POST" action="<?php echo admin_url('gs_lead_sync/clear_logs'); ?>" class="display-inline" style="display:inline; margin-left:8px;">
        <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
        <button type="submit" class="btn btn-danger btn-xs"
                onclick="return confirm('Clear all sync logs?')">
          <i class="fa fa-trash"></i> Clear Log
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($logs)): ?>
    <p class="text-muted">No sync runs recorded yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>#</th>
          <th>Sheet</th>
          <th>Triggered By</th>
          <th>Fetched</th>
          <th>Imported</th>
          <th>Skipped</th>
          <th>Failed</th>
          <th>Started</th>
          <th>Duration</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log):
          $started  = strtotime($log['started_at']);
          $finished = strtotime($log['finished_at']);
          $duration = ($started && $finished) ? ($finished - $started) . 's' : '—';
          $errors   = json_decode($log['error_details'] ?? '[]', true) ?: [];
        ?>
        <tr>
          <td><?php echo $log['id']; ?></td>
          <td><?php echo htmlspecialchars($log['sheet_name'] ?? '(deleted)'); ?></td>
          <td>
            <?php if ($log['triggered_by'] === 'cron'): ?>
              <span class="label label-default">cron</span>
            <?php else: ?>
              <span class="label label-info">manual</span>
            <?php endif; ?>
          </td>
          <td><?php echo $log['rows_fetched']; ?></td>
          <td>
            <?php if ($log['rows_imported'] > 0): ?>
              <span class="label label-success"><?php echo $log['rows_imported']; ?></span>
            <?php else: ?>
              <?php echo $log['rows_imported']; ?>
            <?php endif; ?>
          </td>
          <td><?php echo $log['rows_skipped']; ?></td>
          <td>
            <?php if ($log['rows_failed'] > 0): ?>
              <span class="label label-danger"><?php echo $log['rows_failed']; ?></span>
            <?php else: ?>
              <?php echo $log['rows_failed']; ?>
            <?php endif; ?>
          </td>
          <td><?php echo $log['started_at']; ?></td>
          <td><?php echo $duration; ?></td>
        </tr>
        <?php if (!empty($errors)): ?>
        <tr class="bg-danger">
          <td colspan="9">
            <strong>Errors:</strong>
            <ul class="list-unstyled mbottom0" style="margin-top:4px;">
              <?php foreach ($errors as $err): ?>
              <li><i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php
  $total_pages = ceil($total_logs / $limit);
  if ($total_pages > 1):
  ?>
  <nav>
    <ul class="pagination">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <li class="<?php echo $p == $page ? 'active' : ''; ?>">
        <a href="<?php echo admin_url('gs_lead_sync/sync_log?page=' . $p); ?>"><?php echo $p; ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

  <?php endif; ?>

  <?php $this->load->view('includes/footer_wrapper'); ?>
</div>
<?php init_tail(); ?>
