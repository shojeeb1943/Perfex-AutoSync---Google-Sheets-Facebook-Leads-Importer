<div align="center">

# ⚡ Perfex AutoSync
### Google Sheets & Facebook Leads Importer

[![Version](https://img.shields.io/badge/version-1.2.0-6C63FF?style=for-the-badge)](#)
[![Perfex CRM](https://img.shields.io/badge/Perfex%20CRM-Module-FF6584?style=for-the-badge)](#)
[![PHP](https://img.shields.io/badge/PHP-7.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](#)
[![License](https://img.shields.io/badge/license-Commercial-43D9AD?style=for-the-badge)](#)

**Automatically pull Facebook Ads leads from Google Sheets directly into your Perfex CRM — zero manual entry, zero duplicates.**

[Features](#-features) · [Installation](#-installation) · [Configuration](#-configuration) · [How It Works](#-how-it-works) · [API Reference](#-api-reference) · [Changelog](#-changelog)

---

</div>

## ✨ Features

| | |
|---|---|
| 🗂️ **Multi-Sheet Support** | Connect unlimited Google Sheets, each with its own mapping |
| 🔁 **Smart Deduplication** | Tracks imported rows by ID — never re-imports the same lead |
| 🤖 **Cron Automation** | Auto-sync on a schedule: every 15 min, 30 min, 1 hr, 6 hr, or daily |
| 🧹 **Test Lead Filtering** | Automatically skips Facebook test leads (`<test lead:` marker) |
| 🧩 **Flexible Column Mapping** | Map any sheet column to 15 Perfex CRM lead fields |
| 📝 **Description Builder** | Combine multiple sheet columns into a single CRM description field |
| ⚡ **Manual Sync** | Trigger a sync on-demand with one click from the admin UI |
| 📊 **Detailed Audit Logs** | Every sync run is logged with row counts, timing, and error details |
| 🔗 **URL Auto-Extract** | Paste the full Google Sheets URL — the Sheet ID is extracted automatically |
| 🔍 **Column Detection** | One-click AJAX column detection — no manual column entry needed |

---

## 📋 Requirements

- **Perfex CRM** v2.3+
- **PHP** 7.0+ with `openssl` and `cURL` extensions enabled
- **MySQL / MariaDB**
- **Google Cloud** Service Account with Google Sheets API enabled
- **jQuery** (included with Perfex CRM)

---

## 🚀 Installation

### Step 1 — Download the Module

Clone or download this repository into your Perfex CRM modules directory:

```bash
# Navigate to your Perfex CRM root
cd /path/to/perfex-crm

# Place the module in the modules directory
cp -r "Perfex AutoSync - Google Sheets & Facebook Leads Importer" modules/gs_lead_sync
```

> **Important:** The module folder **must** be named `gs_lead_sync`.

### Step 2 — Activate the Module

1. Log in to Perfex CRM as Administrator
2. Go to **Setup → Modules**
3. Find **Google Sheets Lead Sync** and click **Activate**

Activation automatically creates 3 database tables:
- `gs_lead_sync_sheets` — Sheet configurations
- `gs_lead_sync_imported` — Deduplication tracking
- `gs_lead_sync_logs` — Sync audit trail

### Step 3 — Set Up Google Service Account

1. Open [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable the **Google Sheets API**
4. Navigate to **IAM & Admin → Service Accounts**
5. Create a Service Account and generate a **JSON key**
6. Download the JSON key file

Then share your Google Sheet with the service account email:
```
your-service-account@your-project.iam.gserviceaccount.com
```
Grant **Viewer** access (read-only is sufficient).

### Step 4 — Configure the Module

1. In Perfex CRM, go to **Leads → Google Sheets Sync**
2. Open the **Global Settings** tab
3. Paste the full contents of your Service Account JSON key
4. Save settings

---

## ⚙️ Configuration

### Global Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Service Account JSON** | Google Service Account credentials (full JSON) | — |
| **Enable Cron Sync** | Toggle automatic background sync | `Off` |
| **Cron Interval** | How often to auto-sync | `1 hour` |
| **Skip Test Leads** | Auto-skip Facebook test leads | `On` |

### Sheet Configuration

Each connected sheet has its own configuration:

| Field | Description |
|-------|-------------|
| **Configuration Name** | Friendly label for this sheet (e.g., "Q1 Facebook Campaign") |
| **Spreadsheet ID / URL** | Google Sheet ID or full URL — ID is auto-extracted |
| **Sheet Tab Name** | Worksheet tab name (default: `Sheet1`) |
| **Column Mapping** | Map sheet columns → CRM fields (see below) |
| **Description Columns** | Columns to concatenate into the lead Description field |
| **Unique ID Column** | Column used for deduplication (e.g., `Facebook Lead ID`) |
| **Lead Status** | Default status assigned to all imported leads |
| **Lead Source** | Default source assigned to all imported leads |
| **Is Active** | Enable/disable sync for this sheet |

### Column Mapping

The following CRM fields can be mapped to any sheet column:

| CRM Field | Label |
|-----------|-------|
| `name` | Full Name *(required)* |
| `email` | Email Address |
| `phonenumber` | Phone Number |
| `company` | Company |
| `position` | Position |
| `address` | Address |
| `city` | City |
| `country` | Country |
| `zip` | ZIP / Postal Code |
| `website` | Website |
| `lead_value` | Lead Value |
| `note1` – `note5` | Custom Notes (5 fields) |

> **Tip:** Use **Detect Columns** to auto-populate all mapping dropdowns from your live sheet. No manual column name entry needed.

---

## 🔄 How It Works

```
┌─────────────────────────────────────────────────────────┐
│                    SYNC TRIGGER                         │
│              (Manual Click or Cron Job)                 │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│               SyncEngine::sync_sheet()                  │
│  1. Load sheet configuration from database              │
│  2. Authenticate with Google via JWT / Bearer Token     │
│  3. Fetch all rows from Google Sheets API v4            │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                 FOR EACH DATA ROW                       │
│                                                         │
│  ✓ Check deduplication  →  skip if already imported     │
│  ✓ LeadMapper::map_row()  →  transform to CRM schema    │
│  ✓ Test lead check  →  skip if <test lead: marker       │
│  ✓ Name validation  →  skip if no name field            │
│  ✓ XSS sanitization  →  clean all string fields         │
│  ✓ DB insert  →  write to leads table                   │
│  ✓ Mark imported  →  record in dedup table              │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                   LOG SYNC RUN                          │
│   Records: fetched / imported / skipped / failed        │
│   Includes error details, timestamps, trigger source    │
└─────────────────────────────────────────────────────────┘
```

### Phone Number Handling

Facebook sometimes prefixes phone numbers with `p:` in exported data. The module automatically strips this prefix:

```
p:+1234567890  →  +1234567890
```

### Test Lead Detection

Facebook injects test entries into lead forms during setup. Any row containing `<test lead:` in any cell is automatically skipped when the **Skip Test Leads** setting is enabled.

---

## 📁 Module Structure

```
gs_lead_sync/
├── gs_lead_sync_module.php       # Module entry, hooks, activation
├── composer.json                 # Package metadata
│
├── controllers/
│   └── Gs_lead_sync.php          # Admin controller (12 actions)
│
├── models/
│   ├── SheetConfigModel.php      # Sheet config CRUD
│   └── SyncLogModel.php          # Sync logs & deduplication
│
├── libraries/
│   ├── GoogleSheetsClient.php    # Google Sheets API v4 client (JWT auth)
│   ├── LeadMapper.php            # Column mapping & data transformation
│   └── SyncEngine.php            # Core sync orchestration
│
├── migrations/
│   └── 001_install_gs_lead_sync.php  # Database schema
│
├── views/
│   ├── settings/
│   │   ├── index.php             # Dashboard (3 tabs)
│   │   └── sheet_form.php        # Add/edit sheet config form
│   └── sync_log/
│       └── index.php             # Paginated sync history
│
├── assets/
│   ├── css/gs_lead_sync.css      # Module styles
│   └── js/gs_lead_sync.js        # AJAX column detection & sync triggers
│
└── language/
    └── english_lang.php          # Localization strings
```

---

## 🗄️ Database Schema

### `gs_lead_sync_sheets`
Stores each sheet integration configuration.

```sql
id                INT  PRIMARY KEY AUTO_INCREMENT
name              VARCHAR(255)                    -- Friendly label
spreadsheet_id    VARCHAR(255)                    -- Google Sheet file ID
sheet_tab         VARCHAR(255)  DEFAULT 'Sheet1'  -- Worksheet tab name
lead_status_id    INT                             -- FK → leads_status
lead_source_id    INT                             -- FK → leads_sources
column_mapping    TEXT                            -- JSON: {crm_field: sheet_col}
description_columns TEXT                          -- JSON: [col_name, ...]
id_column         VARCHAR(100)  DEFAULT 'id'      -- Dedup column name
is_active         TINYINT(1)    DEFAULT 1
created_at        DATETIME
updated_at        DATETIME
```

### `gs_lead_sync_imported`
Deduplication tracking — one row per successfully imported lead.

```sql
id                INT  PRIMARY KEY AUTO_INCREMENT
sheet_config_id   INT UNSIGNED                    -- FK → gs_lead_sync_sheets
row_lead_id       VARCHAR(255)                    -- Value from id_column
perfex_lead_id    INT                             -- FK → leads.id
imported_at       DATETIME
UNIQUE KEY (sheet_config_id, row_lead_id)
```

### `gs_lead_sync_logs`
Full audit trail for every sync operation.

```sql
id                INT  PRIMARY KEY AUTO_INCREMENT
sheet_config_id   INT                             -- FK → gs_lead_sync_sheets
triggered_by      VARCHAR(50)   DEFAULT 'manual'  -- 'manual' | 'cron'
rows_fetched      INT           DEFAULT 0
rows_imported     INT           DEFAULT 0
rows_skipped      INT           DEFAULT 0
rows_failed       INT           DEFAULT 0
error_details     TEXT                            -- JSON array of error strings
started_at        DATETIME
finished_at       DATETIME
```

---

## 🔒 Security

| Layer | Implementation |
|-------|----------------|
| **Admin-Only Access** | All routes check `is_admin()` — non-admins are blocked |
| **CSRF Protection** | All forms and AJAX POST requests include CodeIgniter CSRF tokens |
| **XSS Prevention** | `xss_clean()` applied to all lead data before DB insert |
| **SQL Injection** | CodeIgniter Query Builder with parameterized queries throughout |
| **Service Account Validation** | JSON is validated for required fields (`private_key`, `client_email`) before saving |
| **SSL/TLS Enforcement** | Google API calls use `CURLOPT_SSL_VERIFYPEER = true` |
| **Output Escaping** | All view output passes through `htmlspecialchars()` |

---

## 🔌 API Reference

### Controller Endpoints

All endpoints are under `/admin/gs_lead_sync/`.

| Method | Endpoint | Type | Description |
|--------|----------|------|-------------|
| GET | `/` | Page | Dashboard with all sheets and settings |
| POST | `/save_settings` | Form | Save global settings |
| GET | `/add_sheet` | Page | New sheet configuration form |
| GET | `/edit_sheet/{id}` | Page | Edit existing sheet configuration |
| POST | `/save_sheet` | Form | Create or update sheet configuration |
| POST | `/delete_sheet/{id}` | AJAX | Delete sheet and related data |
| POST | `/detect_columns` | AJAX | Fetch column headers from Google Sheet |
| POST | `/sync_now/{id}` | AJAX | Trigger manual sync for a sheet |
| GET | `/sync_log` | Page | Paginated sync history |
| POST | `/clear_logs` | Form | Truncate all sync logs |

### AJAX: `detect_columns`

**Request:**
```json
{
  "spreadsheet_id": "1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms",
  "sheet_tab": "Sheet1"
}
```

**Response (success):**
```json
{
  "status": "success",
  "columns": ["Full Name", "Email", "Phone Number", "Facebook Lead ID", "Campaign Name"]
}
```

**Response (error):**
```json
{
  "status": "error",
  "message": "Unable to access sheet. Ensure the service account has been granted access."
}
```

### AJAX: `sync_now`

**Response (success):**
```json
{
  "status": "success",
  "imported": 12,
  "skipped": 3,
  "failed": 0
}
```

**Response (error):**
```json
{
  "status": "error",
  "message": "Google API authentication failed."
}
```

---

## 🛠️ Troubleshooting

### "Unable to authenticate with Google"

- Verify the Service Account JSON is pasted in full (including `{` and `}`)
- Confirm the JSON contains both `private_key` and `client_email` fields
- Check that the **Google Sheets API** is enabled in your Google Cloud project

### "Sheet not found / Permission denied"

- Open the Google Sheet and click **Share**
- Add the service account email (found in the JSON as `client_email`)
- Grant at least **Viewer** access

### Leads Not Importing

- Confirm the **Unique ID Column** matches an actual column header in your sheet (case-sensitive)
- Check the **Sync Log** tab for error details
- Ensure the **Name** column is mapped — it is required for every lead

### Cron Not Running

- Verify Perfex CRM's cron job is configured on your server:
  ```bash
  * * * * * php /path/to/perfex/index.php cron
  ```
- Confirm **Enable Cron Sync** is toggled on in Global Settings

---

## 📄 Changelog

### v1.2.0
- Custom `GoogleSheetsClient` — removed external Composer dependency
- Improved JWT token caching with 60-second buffer
- Added `description_columns` multi-column concatenation
- URL auto-extraction for Spreadsheet ID field

### v1.1.0
- Added paginated Sync Log view
- AJAX-powered column detection
- Per-sheet Lead Status and Lead Source assignment

### v1.0.0
- Initial release
- Google Sheets API v4 integration
- Deduplication engine
- Cron-based auto-sync
- Admin dashboard with column mapping UI

---

## 👨‍💻 Author

**ByteSIS** — [bytesis.com](https://bytesis.com)

---

<div align="center">

Built for [Perfex CRM](https://perfexcrm.com) · Powered by Google Sheets API v4

</div>
