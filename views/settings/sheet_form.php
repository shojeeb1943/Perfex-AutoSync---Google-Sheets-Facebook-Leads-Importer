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
  $s_col_map   = isset($sheet['column_mapping'])   ? $sheet['column_mapping']   : [];
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
              <input type="text" name="spreadsheet_id" class="form-control" required
                     placeholder="Paste full URL or just the spreadsheet ID"
                     value="<?php echo htmlspecialchars($s_sid, ENT_QUOTES, 'UTF-8'); ?>">
              <small class="text-muted">The ID is the long string between /d/ and /edit in the URL.</small>
            </div>

            <div class="form-group">
              <label>Sheet Tab Name</label>
              <input type="text" name="sheet_tab" class="form-control"
                     placeholder="Sheet1"
                     value="<?php echo htmlspecialchars($s_tab, ENT_QUOTES, 'UTF-8'); ?>">
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
                <?php foreach ((isset($staff_list) ? $staff_list : []) as $st):
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
              Type the exact column header name from your Google Sheet for each CRM field. Leave blank to skip.
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
                    <input type="text"
                           name="column_mapping[<?php echo $crm_key; ?>]"
                           class="form-control"
                           placeholder="e.g. Full Name"
                           value="<?php echo htmlspecialchars(isset($s_col_map[$crm_key]) ? $s_col_map[$crm_key] : '', ENT_QUOTES, 'UTF-8'); ?>">
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading"><h4 class="panel-title">Unique Row ID Column</h4></div>
          <div class="panel-body">
            <div class="form-group">
              <label>ID Column <span class="text-danger">*</span></label>
              <input type="text" name="id_column" class="form-control" required
                     placeholder="e.g. id"
                     value="<?php echo htmlspecialchars($s_id_col, ENT_QUOTES, 'UTF-8'); ?>">
              <small class="text-muted">
                The column whose value uniquely identifies each row (prevents duplicate imports).
                For Facebook leads sheets this is typically <strong>id</strong>.
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
