# CivicWatch — Smart Community Issue Reporting and Tracking System

CivicWatch is a complete, database-driven community issue reporting platform built for local government service teams and the residents they serve. Citizens report problems with photos and map locations, administrators triage and assign work to departments, and department staff record progress and close cases — all with a full audit trail, notifications and analytics.

Built with **core PHP (no framework)**, **MySQL**, **Bootstrap 5**, **Leaflet/OpenStreetMap**, **Chart.js** and **Font Awesome**, it runs locally on **XAMPP** with no paid services.

**Developer:** Ketema Deresa — Software Developer  
**Contact:** [ketemaderesa123@gmail.com](mailto:ketemaderesa123@gmail.com)
      Live:  https://civicwatch.rf.gd/index.php
---

## Table of contents

1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [Project structure](#project-structure)
4. [Requirements](#requirements)
5. [Installation with XAMPP](#installation-with-xampp)
6. [Database setup (phpMyAdmin)](#database-setup-phpmyadmin)
7. [Configuration](#configuration)
8. [Running the application](#running-the-application)
9. [Default accounts](#default-accounts)
10. [Changing the default administrator password](#changing-the-default-administrator-password)
11. [How the system works](#how-the-system-works)
12. [Report status workflow](#report-status-workflow)
13. [Security implementation](#security-implementation)
14. [Database schema](#database-schema)
15. [Folder permissions and uploads](#folder-permissions-and-uploads)
16. [Troubleshooting](#troubleshooting)
17. [Screens and routes](#screens-and-routes)
18. [Customisation](#customisation)
19. [License](#license)

---

## Features

### Public website
- Modern responsive landing page with hero, project introduction, "How it works", issue categories, live community statistics, recent reports, an interactive Leaflet map of resolved/active reports, announcements and contact information.
- All statistics, recent reports, categories and announcements are read live from MySQL — no hard-coded demo data.
- Light/dark theme switch, persisted in the browser.
- Public report detail cards, searchable report references and category browsing.
- Citizen registration and a shared, role-aware sign-in page with separate Citizen / Staff / Admin portals.

### Citizen portal
- Personal dashboard with live counts, recent activity and status breakdown.
- Submit a report with title, category, description, address, priority suggestion, GPS coordinates chosen on an interactive map, and up to five validated photos.
- Track every personal report with search, status/category filters, sorting and pagination.
- Report detail page with photo gallery, live status timeline, assigned department and staff contact details, rejection reasons and completion evidence.
- Add additional photos to an open report (with an audit note).
- Notification centre with unread badges, mark read, mark all read, delete and "open report" links.
- Profile management: name, phone, address, email (current password required) and password change with session rotation.

### Administrator portal
- Executive dashboard with KPI cards, 7-day reporting vs. resolution trend, status distribution chart, department workload, recent reports and recent activity — all computed from the database.
- Report register with full-text search, status/category/department/priority filters, sorting and pagination.
- Report detail workspace: approve, reject (with required reason), assign to a department and staff member, set priority, add internal notes, update status and view the complete timeline.
- User management with role/status filters, account creation, editing, activation/deactivation and deletion safeguards (cannot remove the last active admin, cannot delete staff with open assignments).
- Department management (create, edit, activate/deactivate, delete with referential-integrity guards).
- Category management with icon and colour pickers used throughout the UI and charts.
- Announcement management with audience targeting (everyone / citizens / staff), draft/published states, and automatic notifications on publish.
- Analytics and reports: date-range filters, volume trend, category/status/department breakdowns, resolution metrics and staff performance.
- CSV export that honours the current filters and date range, with UTF-8 BOM and spreadsheet-formula injection protection.

### Department staff portal
- Assignment dashboard with workload summary and recent assignments.
- Assigned report list with search, status filters, sorting and pagination.
- Report workspace: read citizen details and photos, add progress notes, upload completion evidence, move reports to **In Progress**, and mark completed work **Resolved** with a confirmation checkbox.
- Status timeline, evidence gallery and automatic notifications to the reporting citizen.

### Shared
- Role-based access control enforced on every protected page.
- Notification centre shared by all authenticated roles with role-scoped audiences.
- Profile page shared by all roles.
- Responsive sidebar/topbar dashboard layout, accessible forms, empty states, flash messages, confirmation modals and pagination.
- Consistent status/priority badges, icons and colours.

---

## Technology stack

| Layer | Technology |
|---|---|
| Server | Apache (XAMPP) + PHP 8.x |
| Database | MySQL / MariaDB via PDO (`pdo_mysql`) |
| Frontend | HTML5, CSS3, JavaScript (vanilla) |
| UI framework | Bootstrap 5.3 |
| Icons | Font Awesome 6 |
| Maps | Leaflet.js + OpenStreetMap tiles |
| Charts | Chart.js 4 |
| Fonts | Google Fonts (Inter) |
| Security | `password_hash`/`password_verify`, PDO prepared statements, CSRF tokens, output escaping, secure sessions, upload MIME validation |

No Composer, Node.js, npm or framework is required.

---

## Project structure

```
civicwatch/
├── admin/                       # Administrator portal
│   ├── index.php                # Admin dashboard
│   ├── login.php                # Redirects to shared admin sign-in
│   ├── reports.php              # Report register (search/filter/sort/pagination)
│   ├── report_view.php          # Single-report management workspace
│   ├── users.php                # User list and account actions
│   ├── user_form.php            # Create/edit user accounts
│   ├── departments.php          # Department management
│   ├── categories.php           # Issue category management
│   ├── announcements.php        # Announcement publishing
│   ├── analytics.php            # Analytics and report generation
│   └── export_reports.php       # Filtered CSV export
├── user/                        # Citizen portal
│   ├── index.php                # Citizen dashboard
│   ├── submit_report.php        # Report submission with map + photos
│   ├── reports.php              # Personal report list
│   └── report_view.php          # Personal report detail/timeline
├── staff/                       # Department staff portal
│   ├── index.php                # Staff dashboard
│   ├── login.php                # Redirects to shared staff sign-in
│   ├── reports.php              # Assigned report list
│   └── report_view.php          # Progress notes, evidence and resolution
├── config/
│   ├── config.php               # App, database, URL, session and security config
│   └── database.php             # PDO connection factory
├── includes/
│   ├── functions.php            # Shared helpers: auth, CSRF, queries, uploads, formatting
│   ├── public_header.php        # Public site header/navigation
│   ├── public_footer.php        # Public site footer and scripts
│   ├── dashboard_header.php     # Authenticated dashboard layout and sidebar
│   └── dashboard_footer.php     # Dashboard closing markup and scripts
├── assets/
│   ├── css/style.css            # Custom theme, layout and component styles
│   ├── js/app.js                # Theme switch, map helpers, form UX, confirmations
│   └── images/logo.svg          # Brand logo
├── uploads/
│   ├── .htaccess                # Directory listing disabled
│   ├── reports/                 # Citizen report photos
│   │   └── .htaccess            # Script execution blocked
│   └── evidence/                # Staff completion evidence
│       └── .htaccess            # Script execution blocked
├── database/
│   └── civicwatch_db.sql        # Complete schema + sample data + default accounts
├── index.php                    # Public homepage
├── login.php                    # Shared sign-in (Citizen / Staff / Admin portals)
├── register.php                 # Citizen registration
├── logout.php                   # POST-only secure logout
├── notifications.php            # Notification centre
├── profile.php                  # Profile and password management
└── README.md
```

---

## Requirements

- Windows, macOS or Linux
- **XAMPP** with:
  - Apache 2.4+
  - PHP **8.0 or newer** with `pdo_mysql`, `fileinfo`, `mbstring` and `gd` enabled
  - MySQL 5.7+ / MariaDB 10.3+
- A modern browser (Chrome, Edge, Firefox or Safari)
- Internet access for the CDN-hosted Bootstrap, Font Awesome, Leaflet and Chart.js assets, and OpenStreetMap map tiles

> The application itself needs no internet for PHP/MySQL logic; only the map tiles and CDN styling require a connection. For fully offline use, download those libraries into `assets/`.

---

## Installation with XAMPP

### 1. Install XAMPP
1. Download XAMPP from <https://www.apachefriends.org/>.
2. Run the installer and install to the default location, e.g. `C:\xampp`.
3. Open the **XAMPP Control Panel**.

### 2. Start Apache and MySQL
1. Click **Start** next to **Apache**.
2. Click **Start** next to **MySQL**.
3. Both should turn green. If a port is blocked, see [Troubleshooting](#troubleshooting).

### 3. Copy the project into htdocs
Copy the whole `civicwatch` folder so the final path is:

```
C:\xampp\htdocs\civicwatch
```

The folder must contain `index.php` directly inside it:

```
C:\xampp\htdocs\civicwatch\index.php
C:\xampp\htdocs\civicwatch\admin\index.php
...
```

You can rename the folder, but then use that name in the URL instead of `civicwatch`.

---

## Database setup (phpMyAdmin)

### Option A — phpMyAdmin (recommended)
1. Make sure MySQL is running.
2. Open <http://localhost/phpmyadmin>.
3. Click the **Import** tab at the top.
4. Click **Choose File** and select:
   `C:\xampp\htdocs\civicwatch\database\civicwatch_db.sql`
5. Leave the format as **SQL**, the character set as **utf-8**, and scroll down.
6. Click **Import**.
7. Wait for the success message. The database `civicwatch_db` is created automatically if it does not exist.

> The SQL file starts with `CREATE DATABASE IF NOT EXISTS civicwatch_db` and uses it, so you do **not** need to create the database manually.
>
> The script contains `DROP TABLE IF EXISTS` statements. Re-importing it resets the database to the original sample data and deletes any test data you added. Back up important data before re-importing.

### Option B — command line
From `C:\xampp\mysql\bin`:

```bat
mysql.exe -u root -p < "C:\xampp\htdocs\civicwatch\database\civicwatch_db.sql"
```

Press Enter when prompted for a password (the XAMPP default root password is empty).

### Verify the import
In phpMyAdmin, select `civicwatch_db`. You should see nine tables:

`users`, `departments`, `categories`, `reports`, `report_images`, `report_assignments`, `report_updates`, `notifications`, `announcements`.

The sample data includes 1 administrator, 5 department staff, 4 citizens, 9 reports across every workflow stage, 7 report images, timeline updates, assignments, notifications and announcements.

---

## Configuration

Open `config/config.php` and adjust the values if your environment differs.

### Database

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'civicwatch_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

- XAMPP's default MySQL user is `root` with an **empty** password. If you set a MySQL root password, put it in `DB_PASS`.
- If `localhost` gives a socket error on your system, keep `127.0.0.1` (TCP) as shown.

### Application
```php
define('APP_NAME', 'CivicWatch');
define('COMMUNITY_NAME', 'Greenfield City');
define('APP_DEBUG', true);   // Set to false on a public server
```

`APP_DEBUG` controls PHP error display. Set it to `false` before publishing or deploying.

### Developer credit, contact details and map centre
```php
define('DEVELOPER_NAME', 'Ketema Deresa');
define('DEVELOPER_TITLE', 'Software Developer');
define('DEVELOPER_EMAIL', 'ketemaderesa123@gmail.com');

define('CONTACT_EMAIL', DEVELOPER_EMAIL);
define('CONTACT_PHONE', '+251914976416');
define('CONTACT_ADDRESS', 'Oda Bultum University, Chiro, Oromia, Ethiopia');
define('DEFAULT_MAP_LAT', 9.0300);
define('DEFAULT_MAP_LNG', 38.7400);
define('DEFAULT_MAP_ZOOM', 13);
```

The developer name, title and email are shown in the public footer, the dashboard footer and the homepage contact section. `CONTACT_EMAIL` reuses `DEVELOPER_EMAIL`, so changing the developer email updates every contact link at once.

Change the coordinates to centre the map picker on your own community. The sample data is fictional and centred around Addis Ababa.

### Uploads
```php
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per image
define('MAX_REPORT_IMAGES', 5);              // per report
```

Allowed formats: JPG, PNG, WEBP and GIF (validated by actual file contents, not just extension).

### Base URL
No manual URL setting is required. `config/config.php` derives `BASE_URL` from the running script, so the app works at:

- `http://localhost/civicwatch/`
- `http://localhost/anything-else/`
- `http://127.0.0.1:8123/` with PHP's built-in server

---

## Running the application

After Apache and MySQL are running and the database is imported, open:

**<http://localhost/civicwatch/>**

Sign in pages:

- Citizen: <http://localhost/civicwatch/login.php?role=citizen>
- Staff: <http://localhost/civicwatch/login.php?role=staff>
- Admin: <http://localhost/civicwatch/login.php?role=admin>

`admin/login.php` and `staff/login.php` also exist and redirect to the correct shared portal.

### Optional: PHP built-in server
If Apache is unavailable you can run the app from the project folder:

```bat
cd C:\xampp\htdocs\civicwatch
C:\xampp\php\php.exe -S 127.0.0.1:8123 -t .
```

Then open <http://127.0.0.1:8123/>.

---

## Default accounts

| Role | Email | Password | Portal |
|---|---|---|---|
| Administrator | `admin@civicwatch.local` | `Admin@123` | Admin |
| Citizen | `amina@example.com` | `Citizen@123` | Citizen |
| Department Staff | `yonas.staff@civicwatch.local` | `Staff@123` | Staff |

Additional sample staff accounts (password `Staff@123`):

| Name | Email | Department |
|---|---|---|
| Meron Haile | `meron.staff@civicwatch.local` | Water & Sanitation |
| Samuel Girma | `samuel.staff@civicwatch.local` | Power & Lighting |
| Hana Bekele | `hana.staff@civicwatch.local` | Waste Management |
| Dawit Tadesse | `dawit.staff@civicwatch.local` | Drainage & Environment |

Additional sample citizens (password `Citizen@123`):

| Name | Email |
|---|---|
| Liya Abebe | `liya@example.com` |
| Daniel Tesfaye | `daniel@example.com` |
| Sara Mekonnen | `sara@example.com` |

> **Security notice:** these credentials are for local demonstration only. Change the administrator password immediately and never deploy the seed database to a public server unchanged.

---

## Changing the default administrator password

### Recommended — through the application
1. Sign in to the **Admin** portal with `admin@civicwatch.local` / `Admin@123`.
2. Open **Profile** from the sidebar or the account menu.
3. In **Change password**, enter the current password, then the new password twice.
4. Submit. The password is hashed with `password_hash()` and the session ID is rotated automatically.
5. Confirm by signing out and signing in again.

Citizens and staff can change their own passwords the same way via **Profile**.

### Optional — reset via phpMyAdmin
If the admin password is lost:
1. Generate a new hash with PHP:
   ```bat
   C:\xampp\php\php.exe -r "echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);"
   ```
2. In phpMyAdmin, open `civicwatch_db` → `users` → edit the admin row.
3. Replace the `password_hash` value with the generated hash and save.
4. Sign in with the new password.

Never store or paste plain-text passwords into the database.

You can also create a new administrator from **Users → Add user** while signed in as an existing admin.

---

## How the system works

1. **A citizen registers** (or signs in with a sample account) and submits a report: category, title, description, address, suggested priority, map coordinates and up to five photos.
2. The report enters the workflow as **Pending** and administrators receive a notification.
3. **An administrator reviews** the report and either:
   - moves it to **Under Review** / **Approved**, then assigns it to a department and staff member, or
   - **rejects** it with a mandatory explanation.
4. **The assigned staff member** sees the case in their portal, adds progress notes, uploads evidence photos and moves it to **In Progress**.
5. When the work is complete, staff mark it **Resolved** (with a confirmation checkbox). Completion evidence and notes are visible to the citizen.
6. **The citizen** tracks progress on the report timeline, receives notifications at every meaningful change, and can add extra photos while the case is open.
7. **Administrators monitor** volumes, resolution rates, category/department breakdowns and staff performance in Analytics, and export filtered CSV reports.

---

## Report status workflow

```
Pending ──► Under Review ──► Approved ──► Assigned ──► In Progress ──► Resolved
   │              │
   └──────────────┴──────────────────────────────────────────────► Rejected
```

| Status | Key | Meaning |
|---|---|---|
| Pending | `pending` | Newly submitted, awaiting triage |
| Under Review | `under_review` | Administrator is validating the report |
| Approved | `approved` | Confirmed valid, awaiting assignment |
| Assigned | `assigned` | Allocated to a department and staff member |
| In Progress | `in_progress` | Field work underway |
| Resolved | `resolved` | Work completed and confirmed |
| Rejected | `rejected` | Not actioned; reason required and shown to the citizen |

Priority levels: **Low**, **Medium**, **High**, **Critical**.

Each status change writes to `report_updates`, updates `reports.updated_at` / `resolved_at`, and notifies the citizen and/or assigned staff.

---

## Security implementation

| Risk | Mitigation |
|---|---|
| Password storage | `password_hash()` with bcrypt; verification with `password_verify()`. No plain-text passwords anywhere. |
| SQL injection | Every query uses **PDO prepared statements** with bound parameters. Dynamic `ORDER BY` values come from allow-lists, never user input. |
| Cross-site scripting (XSS) | All dynamic output passes through `htmlspecialchars()` via the `e()` helper. |
| CSRF | Random per-session token, validated on every state-changing POST. Missing/invalid tokens are rejected with HTTP 403. |
| Session security | Custom session name, `HttpOnly`, `SameSite=Lax`, strict mode, `use_only_cookies`, idle timeout, regeneration on login/logout/password change. |
| Role-based access | `require_login()`, `require_role()` and `require_guest()` guards on every page; cross-role access returns 403. |
| File uploads | Extension allow-list, size limit, `finfo` MIME verification, `getimagesize()` check, random filenames, storage outside executable paths, Apache rules disable script execution in `uploads/`. |
| Brute force | Failed-login throttling (attempt counter with temporary lockout). |
| Header hardening | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`. |
| Spreadsheet injection | CSV export prefixes formula-leading values (`=`, `+`, `-`, `@`) with an apostrophe. |
| Authorization on records | Citizens and staff can only access their own/assigned reports; administrators can access all. |
| Auditing | `report_updates` records who changed what and when; assignment history kept in `report_assignments`. |

Additional notes:
- Registration restricts public sign-ups to the **citizen** role; admin and staff accounts are created by administrators.
- Each portal only accepts credentials belonging to that role. Trying an admin login in the citizen portal shows an explicit portal-mismatch message.
- Logout is POST-only and CSRF-protected.

---

## Database schema

| Table | Purpose | Key relationships |
|---|---|---|
| `departments` | Service departments | referenced by `users`, `reports`, `report_assignments` |
| `users` | Admins, staff and citizens | `department_id → departments.id` |
| `categories` | Issue categories with icon/colour | referenced by `reports` |
| `reports` | Core report records | `user_id → users.id`, `category_id → categories.id`, `department_id → departments.id`, `assigned_staff_id → users.id` |
| `report_images` | Report photos and completion evidence | `report_id → reports.id`, `uploaded_by → users.id` |
| `report_assignments` | Assignment history | `report_id`, `department_id`, `assigned_to`, `assigned_by` |
| `report_updates` | Status changes, notes, timeline | `report_id`, `user_id` |
| `notifications` | Per-user notification inbox | `user_id → users.id`, optional `report_id` |
| `announcements` | Public/role-targeted announcements | `created_by → users.id` |

All tables use InnoDB, `utf8mb4`, primary keys, foreign keys with sensible `ON DELETE` behaviour, timestamps and indexes on frequently filtered columns (status, category, department, staff, created date, notification read state).

Default categories seeded by the SQL:

- Road Damage
- Water Supply Problems
- Electricity Problems
- Waste Management
- Broken Streetlights
- Drainage Problems
- Public Infrastructure
- Other Community Issues

---

## Folder permissions and uploads

- The web server must be able to **write** to:
  - `uploads/reports/`
  - `uploads/evidence/`
- On Windows/XAMPP this normally works out of the box. If uploads fail, right-click the `uploads` folder → **Properties** → **Security** and grant the Apache service account (or `Everyone` on a local test machine) **Modify** permission.
- On Linux:
  ```bash
  sudo chown -R www-data:www-data uploads
  sudo chmod -R 775 uploads
  ```
- Uploaded files are renamed with random hex names. Original filenames are never trusted or reused.
- `uploads/reports/.htaccess` and `uploads/evidence/.htaccess` disable PHP/CGI execution and deny direct access to executable extensions.
- To remove all user-uploaded files while keeping the sample images, delete non-`sample-*` files inside these folders.

---

## Troubleshooting

### "Can't connect to MySQL server" / database error page
- Make sure **MySQL** is started in the XAMPP Control Panel.
- Check `DB_HOST`, `DB_PORT`, `DB_USER` and `DB_PASS` in `config/config.php`.
- Confirm the database exists in phpMyAdmin and that `civicwatch_db.sql` imported without errors.

### "Unknown database 'civicwatch_db'"
The SQL file was not imported, or was imported into a different MySQL instance. Re-import `database/civicwatch_db.sql`.

### Apache won't start (port 80 in use)
- Another web server (IIS, Skype, another Apache) may be using port 80.
- Either stop that service or change Apache's port in `C:\xampp\apache\conf\httpd.conf` (`Listen 80` → `Listen 8080`), then use <http://localhost:8080/civicwatch/>.

### MySQL won't start (port 3306 in use)
- Another MySQL instance is running. Stop it or change the XAMPP MySQL port and update `DB_PORT`.

### Blank page or HTTP 500
- Temporarily set `APP_DEBUG` to `true` in `config/config.php` to see the error.
- Check `C:\xampp\apache\logs\error.log`.
- Confirm PHP 8+ is active and the `pdo_mysql` extension is enabled in `C:\xampp\php\php.ini`:
  ```ini
  extension=pdo_mysql
  extension=fileinfo
  extension=mbstring
  extension=gd
  ```

### Uploads fail
- Check the PHP limits in `php.ini`:
  ```ini
  upload_max_filesize = 5M
  post_max_size = 12M
  ```
- Ensure `uploads/reports` and `uploads/evidence` are writable.
- Only JPG, PNG, WEBP and GIF files up to 5 MB are accepted.

### Map does not appear
- Leaflet and OpenStreetMap tiles load from the internet. Check your connection and whether a firewall or ad-blocker blocks `tile.openstreetmap.org`.
- The rest of the application works without network access.

### Styles or icons missing
- Bootstrap, Font Awesome, Chart.js and Google Fonts are loaded from CDNs. If you need offline operation, download them locally and update `includes/public_header.php`, `includes/public_footer.php` and `includes/dashboard_footer.php`.

### Session expired / "security token is invalid"
- Sessions expire after one hour of inactivity. Reload the page and try again.
- The token rotates after sign-in, sign-out and password changes; always use the form from the freshly loaded page.

### Forgot the admin password
See [Changing the default administrator password](#changing-the-default-administrator-password) and reset the hash through phpMyAdmin.

---

## Screens and routes

### Public
| Route | Description |
|---|---|
| `/civicwatch/index.php` | Homepage, live statistics, map and announcements |
| `/civicwatch/register.php` | Citizen registration |
| `/civicwatch/login.php?role=citizen` | Citizen sign-in |
| `/civicwatch/login.php?role=staff` | Staff sign-in |
| `/civicwatch/login.php?role=admin` | Administrator sign-in |
| `/civicwatch/logout.php` | Secure POST logout |

### Admin
| Route | Description |
|---|---|
| `/civicwatch/admin/index.php` | Dashboard |
| `/civicwatch/admin/reports.php` | Report register |
| `/civicwatch/admin/report_view.php?id=1` | Report management |
| `/civicwatch/admin/users.php` | User management |
| `/civicwatch/admin/user_form.php` | Create/edit user |
| `/civicwatch/admin/departments.php` | Departments |
| `/civicwatch/admin/categories.php` | Categories |
| `/civicwatch/admin/announcements.php` | Announcements |
| `/civicwatch/admin/analytics.php` | Analytics |
| `/civicwatch/admin/export_reports.php` | CSV export |

### Citizen
| Route | Description |
|---|---|
| `/civicwatch/user/index.php` | Dashboard |
| `/civicwatch/user/submit_report.php` | Submit report |
| `/civicwatch/user/reports.php` | My reports |
| `/civicwatch/user/report_view.php?id=1` | Report tracking |

### Staff
| Route | Description |
|---|---|
| `/civicwatch/staff/index.php` | Dashboard |
| `/civicwatch/staff/reports.php` | Assigned reports |
| `/civicwatch/staff/report_view.php?id=1` | Update progress / resolve |

### Shared
| Route | Description |
|---|---|
| `/civicwatch/notifications.php` | Notification centre |
| `/civicwatch/profile.php` | Profile and password |

---

## Customisation

- **Branding / palette:** edit CSS custom properties at the top of `assets/css/style.css` (navy, teal, surfaces, radii).
- **Community name, contact details, map centre:** `config/config.php`.
- **Categories and departments:** manage in the admin UI (changes appear immediately across the site). You can also edit the seed data in `database/civicwatch_db.sql` before importing.
- **Announcements:** manage in the admin UI; published announcements appear on the homepage and generate notifications.
- **Upload limits:** `MAX_UPLOAD_BYTES` and `MAX_REPORT_IMAGES` in `config/config.php`; keep `php.ini` limits at least as large.
- **Theme:** users can switch light/dark mode from the public navbar or dashboard topbar. The preference is stored in `localStorage` under `civicwatch-theme`.

---

## License

Created by **Ketema Deresa**, Software Developer, as project and full-stack portfolio demonstration. Contact: [ketemaderesa123@gmail.com](mailto:ketemaderesa123@gmail.com).

You may use, modify and extend it for learning and portfolio purposes. If you deploy it publicly, replace the sample data, change all default passwords, set `APP_DEBUG` to `false`, and configure HTTPS.
