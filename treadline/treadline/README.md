# TREADLINE — Smart Fleet Tyre Tracking & Management System

A fleet tyre lifecycle management ERP: vehicles, tyre positions, inspections,
stock, rotations, repairs, retreads, reports and analytics.

## Requirements

- PHP 8.2+ with `pdo_mysql` extension
- MySQL 8+ (or MariaDB 10.6+)
- A web server (Apache/Nginx) or PHP's built-in server for local testing

## Quick Start (local testing with PHP's built-in server)

```bash
# 1. Create the database and load the schema
mysql -u root -p -e "CREATE DATABASE treadline CHARACTER SET utf8mb4;"
mysql -u root -p treadline < sql/schema.sql

# 2. Set your DB credentials (edit config/db.php, or use env vars)
export TREADLINE_DB_HOST=localhost
export TREADLINE_DB_NAME=treadline
export TREADLINE_DB_USER=root
export TREADLINE_DB_PASS=yourpassword

# 3. Fix the default admin password (schema.sql ships with a placeholder hash)
php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
# copy the output and run:
mysql -u root -p treadline -e "UPDATE users SET password_hash='PASTE_HASH_HERE' WHERE username='admin';"

# 4. Serve the app
php -S localhost:8000

# 5. Open http://localhost:8000 and log in with admin / admin123
```

## Quick Start (one-click installer)

Instead of steps 1–3 above, you can upload the whole folder to any PHP+MySQL
host and simply visit `setup.php` in your browser. It will create the
database, load the schema, and set a real password hash for you. **Delete
setup.php after installing** for security.

## Deploying to a normal Apache/Nginx host (cPanel, shared hosting, etc.)

1. Upload the entire `treadline/` folder contents to your web root (e.g. `public_html`).
2. Create a MySQL database and user in your hosting control panel.
3. Visit `https://yourdomain.com/setup.php`, fill in the DB details, and install.
4. Delete or rename `setup.php` once done.
5. Log in with the admin password you chose during setup.

## Default Login

- Username: `admin`
- Password: `admin123` (or whatever you set during `setup.php`)

**Change this immediately in production** via the Users page (as ADMIN).

## Roles

| Role | Access |
|---|---|
| ADMIN | Full access, including Users & Settings |
| FLEET_MANAGER | Vehicles, tyres, inspections, reports, analytics, inventory |
| TYRE_INSPECTOR | Inspections, tyre readings, photos, scanner, checklist |
| SUPERVISOR | Review/approve inspections and tyre replacements |
| MANAGEMENT | Read-only dashboard, analytics, reports |

## Importing data from your previous system

Both importers accept **.csv (any delimiter) or native .xlsx** files — no need
to fight with "Save As" settings. Common real-world export quirks are handled
automatically:
- Excel on non-US locales often exports CSV with `;` as the separator instead
  of `,` — the delimiter is auto-detected per file (comma, semicolon, or tab).
- The UTF-8 byte-order-mark (BOM) that Windows/Excel prepends to CSV files is
  stripped automatically.
- `.xlsx` workbooks are read directly (first sheet), with no need to convert
  to CSV first. Requires the PHP `zip` and `xml`/`dom` extensions, which are
  enabled on virtually all hosts by default; if not, the importer tells you
  exactly which one is missing rather than failing silently.

Two importers are built in and matched to real export formats:

- **Fleet → Import Vehicles** — expects columns:
  `plate_number, fleet_number, vehicle_type, make, model, year, vin, branch, department, driver_name, current_odometer, status`
  Unknown branches/vehicle types are auto-created; known status text
  (Active, Breakdown, Escalate to Workshop, In Workshop, Out of Service,
  Written Off (Accident)) is mapped automatically. Re-importing the same
  plate number updates the existing vehicle instead of duplicating it.

- **Inventory → Import Tyres** — expects a Fleetio-style parts export
  with columns: `Fleetio ID, Inventory no., Description, Category,
  Manufacturer, Manufacturer Part Number, Measurement Unit, Total Quantity,
  Unit Cost (KES)`. Only rows where Category contains "Tyre" are imported by
  default. Tyre size is auto-detected from the Description text. Total
  Quantity creates that many individually-serialised stock tyres. Re-running
  the same file is safe (existing serials are skipped, not duplicated).

Both importers are under their respective sidebar sections and will tell you
exactly which rows succeeded, were skipped, or need attention.

## Key features

- Fully **editable tyre-map layouts**: each Vehicle Type has a visual row-builder
  (Fleet → Vehicle Types) where an admin defines exactly which axles exist, which
  are driven, and what position codes each tyre slot uses — no code changes
  needed for a new configuration. Ships with correct layouts for cars/pickups,
  6-wheelers (Isuzu FRR/NQR/NMR), double-drive-axle prime movers, tri-axle
  trailers (P1–P12), forklifts (no spare), and tuktuks (3 tyres, no spare).
- **Trailers can be registered standalone** and linked to the truck/prime mover
  that tows them via a "Towed By" field — shown on the trailer's profile page
  and editable from the vehicle edit form.
- **Full edit capability**, not just add/delete: Vehicles (including quick
  driver reassignment and towing link right on the profile page), Tyres
  (brand/size/PSI spec from the tyre detail popup), Users (profile fields plus
  a separate admin-only **Reset Password** action), Branches, Suppliers, and
  Vehicle Types all support editing in place.
- Dark-sidebar ERP shell with branch/group switching, global search, alerts
- Interactive tyre-position map per vehicle (4x2, 4x4, 6x4 dual-rear, trailer,
  bus layouts) with **drag-and-drop** fitting, rotation and swapping
- Configurable number of spare slots per vehicle (+ / − Spare buttons)
- Full tyre lifecycle: stock → install → inspect → rotate → transfer → repair
  → retread → remove/scrap, with a complete, non-destructible movement history
- Inspections with inner/center/outer tread readings, PSI, a full digital
  condition checklist, photo capture, and an automatically computed 0–100
  tyre health score
- Automatic alerts (critical tread, low/high PSI, sidewall/tread damage,
  foreign objects, repeated repairs)
- Reports Center with 18 report types, CSV/Excel export, and print-optimised
  A4 layouts for the Tyre Manifest, Fleet Manifest and Inspection Report
  (use your browser's "Print → Save as PDF" for PDF output)
- Camera-based QR/barcode scanner (via jsQR) with manual-entry fallback
- All thresholds (critical tread mm, watch tread mm, PSI tolerance, health
  score bands) are configurable under Settings — nothing is hardcoded per
  manufacturer spec

## Notes on "PDF generation"

Reports use print-optimised HTML/CSS (`report_manifest.php`,
`report_inspection.php`, `report_fleet_manifest.php`, `report_view.php`).
Click **Print / Save PDF** and choose "Save as PDF" in the browser's print
dialog — this avoids a heavyweight PDF library dependency while producing
clean, professional A4 output. If you need server-generated PDF files
specifically (e.g. for emailing), drop in a library such as `dompdf` or
`mpdf` and wire it into these same report templates.

## Folder structure

```
treadline/
├── api/              AJAX endpoints (JSON in/out, CSRF-protected)
├── assets/           css/js
├── config/           db.php, config.php (bootstraps session + helpers)
├── exports/          CSV export endpoints
├── includes/         shared head/foot/sidebar/topbar/functions
├── sql/              schema.sql (full schema + seed data)
├── uploads/          inspection photos land here
├── setup.php         one-click installer
└── *.php             one file per page
```

## Security notes for production

- Delete `setup.php` after installing
- Serve over HTTPS
- Set real, unique passwords for all seeded/demo accounts
- `config/`, `sql/` and `*.log` are blocked via `.htaccess`; if you're on
  Nginx, add equivalent `location` blocks denying those paths
