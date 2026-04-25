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
  // Safe defaults when adding a new sheet ($sheet is null)
  $s_name         = isset($sheet['name'])            ? $sheet['name']            : '';
  $s_sid          = isset($sheet['spreadsheet_id'])  ? $sheet['spreadsheet_id']  : '';
  $s_tab          = isset($sheet['sheet_tab'])        ? $sheet['sheet_tab']        : 'Sheet1';
  $s_id_col       = isset($sheet['id_column'])        ? $sheet['id_column']        : 'id';
  $s_status_id    = isset($sheet['lead_status_id'])   ? $sheet['lead_status_id']   : '';
  $s_source_id    = isset($sheet['lead_source_id'])   ? $sheet['lead_source_id']   : '';
  $s_assignee     = isset($sheet['default_assignee']) ? $sheet['default_assignee'] : '';
  $s_is_active    = isset($sheet['is_active'])        ? $sheet['is_active']        : 1;
  $s_col_map      = isset($sheet['column_mapping'])   ? $sheet['column_mapping']   : [];
  $s_desc_cols    = isset($sheet['description_columns']) ? $sheet['description_columns'] : [];
  $has_mapping    = !empty($s_col_map);
  ?>

  <form method="POST" action="<?php echo admin_url('gs_lead_sync/save_sheet'); ?>" id="sheet-config-form">
    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
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
                     placeholder="e.g. Residential Interior — Jan 2026"
                     value="<?php echo htmlspecialchars($s_name); ?>">
            </div>

            <div class="form-group">
              <label>Google Spreadsheet ID or URL <span class="text-danger">*</span></label>
              <input type="text" name="spreadsheet_id" id="gs-spreadsheet-id" class="form-control" required
                     placeholder="Paste full URL or just the spreadsheet ID"
                     value="<?php echo htmlspecialchars($s_sid); ?>">
              <small class="text-muted">The ID is the long string between /d/ and /edit in the URL.</small>
            </div>

            <div class="form-group">
              <label>Sheet Tab Name</label>
              <input type="text" name="sheet_tab" id="gs-sheet-tab" class="form-control"
                     placeholder="Sheet1"
                     value="<?php echo htmlspecialchars($s_tab); ?>">
            </div>

            <button type="button" id="gs-detect-columns" class="btn btn-info">
              <i class="fa fa-search"></i> Detect Columns
            </button>
            <span id="gs-detect-status" class="text-muted mleft5"></span>

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
                  <?php echo htmlspecialchars($ls['name']); ?>
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
                  <?php echo htmlspecialchars($ls['name']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Default Assignee <small class="text-muted">(staff member who owns imported leads)</small></label>
              <select name="default_assignee" class="form-control">
                <option value="">— Unassigned —</option>
                <?php foreach (($staff_list ?? []) as $st):
                    $st_name = trim(($st['firstname'] ?? '') . ' ' . ($st['lastname'] ?? ''));
                    if ($st_name === '') { $st_name = $st['email'] ?? ('Staff #' . $st['staffid']); }
                ?>
                <option value="<?php echo (int)$st['staffid']; ?>"
                  <?php echo ($s_assignee !== '' && (int)$s_assignee === (int)$st['staffid']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($st_name, ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Without an assignee, leads may be invisible to non-admin staff depending on permissions.</small>
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

        <div id="gs-mapping-section" <?php echo $has_mapping ? '' : 'style="display:none;"'; ?>>

          <div class="panel panel-default">
            <div class="panel-heading"><h4 class="panel-title">Column Mapping</h4></div>
            <div class="panel-body">
              <p class="text-muted">
                For each CRM field, select the matching column from your sheet. Leave blank to skip.
              </p>
              <table class="table table-condensed" id="gs-mapping-table">
                <thead>
                  <tr>
                    <th style="width:35%">CRM Field</th>
                    <th>Sheet Column</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($crm_fields as $crm_key => $crm_label): ?>
                  <tr>
                    <td>
                      <?php echo htmlspecialchars($crm_label); ?>
                      <?php if ($crm_key === 'name'): ?><span class="text-danger">*</span><?php endif; ?>
                    </td>
                    <td>
                      <select name="column_mapping[<?php echo $crm_key; ?>]"
                              class="form-control gs-col-select"
                              data-crm-field="<?php echo $crm_key; ?>">
                        <option value="">— Skip —</option>
                        <?php
                        $saved_col = isset($s_col_map[$crm_key]) ? $s_col_map[$crm_key] : '';
                        if ($saved_col !== ''):
                        ?>
                        <option value="<?php echo htmlspecialchars($saved_col); ?>" selected>
                          <?php echo htmlspecialchars($saved_col); ?>
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
            <div class="panel-heading"><h4 class="panel-title">Description Builder</h4></div>
            <div class="panel-body">
              <p class="text-muted">
                Select columns whose values will be concatenated into the CRM
                <strong>Description</strong> field (apartment size, design type, Bengali custom fields, etc.).
              </p>
              <div id="gs-description-columns">
                <?php foreach ($s_desc_cols as $col): ?>
                <div class="checkbox">
                  <label>
                    <input type="checkbox" name="description_columns[]"
                           value="<?php echo htmlspecialchars($col); ?>" checked>
                    <?php echo htmlspecialchars($col); ?>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="panel panel-default">
            <div class="panel-heading"><h4 class="panel-title">Unique ID Column</h4></div>
            <div class="panel-body">

              <div class="form-group">
                <label>Unique ID Column <small class="text-muted">(Facebook Lead ID column)</small></label>
                <select name="id_column" id="gs-id-column" class="form-control">
                  <option value="<?php echo htmlspecialchars($s_id_col); ?>" selected>
                    <?php echo htmlspecialchars($s_id_col); ?>
                  </option>
                </select>
                <small class="text-muted">Column containing the unique Facebook lead ID (values like l:995195976...).</small>
              </div>

            </div>
          </div>

        </div><!-- /#gs-mapping-section -->

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

<script>
var GS_DETECT_URL = '<?php echo admin_url("gs_lead_sync/detect_columns"); ?>';
var GS_CSRF_NAME  = '<?php echo $this->security->get_csrf_token_name(); ?>';
var GS_CSRF_HASH  = '<?php echo $this->security->get_csrf_hash(); ?>';

<?php if ($has_mapping): ?>
// Pre-load saved columns for edit mode so dropdowns are fully populated
var GS_SAVED_COLUMNS = <?php
    $all_saved = array_values(array_unique(array_filter(array_merge(
        array_values($s_col_map),
        $s_desc_cols,
        [$s_id_col]
    ))));
    echo json_encode($all_saved);
?>;
$(function() { gsPopulateSelects(GS_SAVED_COLUMNS); });
<?php endif; ?>
</script>

<?php init_tail(); ?>
