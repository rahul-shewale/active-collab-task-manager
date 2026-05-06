# Task Manager Dashboard — Core PHP OOP

A full conversion of the original Laravel + React task manager into **Core PHP 8.1+ OOP** with **jQuery, Bootstrap 5, and vanilla JS**.

---

## Stack

| Layer     | Original          | Converted to                         |
|-----------|-------------------|--------------------------------------|
| Backend   | Laravel 11        | Core PHP 8.1+ OOP (no framework)     |
| Auth      | Laravel Sanctum   | Custom token auth (SHA-256 hashed)   |
| DB access | Eloquent ORM      | PDO wrapper (`App\Core\DB`)          |
| Cache     | Laravel Cache     | DB-backed cache (`cache_store` table)|
| Frontend  | React + Vite      | jQuery 3.7 + Bootstrap 5.3           |
| HTTP      | Axios             | jQuery `$.ajax`                      |
| Routing   | React Router      | Custom PHP router + tab switching    |
| Services  | Laravel Http      | `curl` / `curl_multi` (parallel)     |

---

## Project Structure

```
php-task-manager/
├── app/
│   ├── Core/
│   │   ├── Bootstrap.php   # Config loader, autoloader, session
│   │   ├── DB.php          # PDO wrapper with insert/update/delete helpers
│   │   ├── Cache.php       # DB-backed cache (replaces Laravel Cache)
│   │   ├── Auth.php        # Token auth (replaces Sanctum)
│   │   └── Router.php      # HTTP router + Request + Response
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── TaskController.php
│   │   ├── UserController.php
│   │   ├── ReportController.php
│   │   ├── SyncController.php
│   │   └── ActiveCollabController.php
│   └── Services/
│       ├── ActiveCollabService.php   # curl_multi parallel requests
│       ├── TrelloService.php
│       ├── MantisService.php
│       └── HubstaffService.php
├── views/
│   ├── report.php          # Task Report HTML fragment
│   ├── ac_members.php
│   ├── ac_projects.php
│   ├── ac_managers.php
│   └── ac_clients.php
├── assets/
│   ├── css/app.css
│   └── js/
│       ├── api.js          # API helper (mirrors original api.js)
│       ├── ac_shared.js    # Shared AC card renderer + escHtml
│       ├── report.js       # Task Report + Hubstaff rendering
│       ├── ac_members.js
│       ├── ac_projects.js
│       ├── ac_managers.js
│       ├── ac_clients.js
│       └── app.js          # Auth, tab routing, sync, auto-sync
├── public/
│   ├── index.php           # Main HTML shell
│   ├── api.php             # API entry point (all /api/* routes)
│   └── .htaccess           # Apache rewrite rules
├── storage/
│   └── hubstaff_refresh_token.txt  (auto-created on first sync)
├── schema.sql              # Full DB schema
└── config.php              # All credentials & settings
```

---

## Setup

### 1. Requirements

- PHP 8.1+  (with `pdo_mysql`, `curl`, `json` extensions)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled, or Nginx

### 2. Database

```bash
mysql -u root -p < schema.sql
```

### 3. Configuration

Copy and edit:

```bash
cp config.php config.php          # already present — just fill in values
```

Required fields in `config.php`:

```php
'db' => [
    'host'     => '127.0.0.1',
    'database' => 'task_dashboard',
    'username' => 'root',
    'password' => 'your_password',
],
'trello' => [
    'key'   => 'YOUR_TRELLO_API_KEY',
    'token' => 'YOUR_TRELLO_TOKEN',
],
'mantis' => [
    'base_url' => 'https://your-mantis.example.com',
    'token'    => 'YOUR_MANTIS_API_TOKEN',
],
'hubstaff' => [
    'org_id'        => 'YOUR_HUBSTAFF_ORG_ID',
    'refresh_token' => 'YOUR_HUBSTAFF_REFRESH_TOKEN',
],
'activecollab' => [
    'base_url' => 'https://your-ac-instance.example.com',
    'token'    => 'YOUR_ACTIVECOLLAB_TOKEN',
],
```

### 4. Web Server

**Apache** — point `DocumentRoot` to `public/` and enable `mod_rewrite`.

**Nginx** example:

```nginx
server {
    root /var/www/php-task-manager/public;
    index index.php;

    location / {
        try_files $uri $uri/ @php;
    }

    location @php {
        rewrite ^/api/(.*)$ /api.php last;
        rewrite ^ /index.php last;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Database migrations

After importing `task_dashboard_php.sql`, run the idempotent migration script
to ensure the ActiveCollab persistence tables exist on already-running
installs:

```bash
php bin/migrate.php
```

This creates `ac_users`, `ac_companies`, `ac_projects`, `ac_tasks`, and
`ac_sync_state` if they're missing. Safe to re-run.

### 6. Cron-driven sync

Every dashboard data source (Trello, Mantis, Hubstaff, **and** ActiveCollab)
is now refreshed exclusively by a background cron job — the dashboard
itself only reads from MySQL.

Add this line to the crontab of whatever user can run `php`:

```cron
*/15 * * * * php /var/www/html/task-manager/bin/cron.php >> /var/www/html/task-manager/storage/cron.log 2>&1
```

Manual ad-hoc runs:

```bash
# CLI
php bin/cron.php

# From the dashboard
Click the "Sync Data" button in the top navbar.
# (POST /api/sync/cron — auth-protected, same orchestration as the cron job)
```

The "Last synced" label next to the Sync Data button is fed by
`GET /api/sync/status`.

### 7. First Login

Create an admin user directly in the DB:

```sql
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES (
  'Admin',
  'admin@example.com',
  '$2y$12$...', -- bcrypt hash of your password
  'admin',
  NOW(), NOW()
);
```

Or use PHP to generate the hash:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

---

## All Preserved Features

| Feature                         | Status |
|---------------------------------|--------|
| Login / Logout (token auth)     | ✅ |
| Task Report (due-date sections) | ✅ |
| Overdue / Today / Upcoming tabs | ✅ |
| Board grouping with colours     | ✅ |
| Hubstaff time tracking cards    | ✅ |
| Hubstaff Day / Week / Month toggle | ✅ |
| AC Members view + search        | ✅ |
| AC Projects view                | ✅ |
| AC Managers view + search       | ✅ |
| AC Clients view + search        | ✅ |
| 15-min AC cache (DB-backed)     | ✅ |
| Trello sync                     | ✅ |
| Mantis sync                     | ✅ |
| Hubstaff sync + token rotation  | ✅ |
| Sync All + sync logs            | ✅ |
| Cron-driven sync every 15 min   | ✅ |
| Manual "Sync Data" button       | ✅ |
| parallel curl_multi requests    | ✅ |
| Pagination on task list         | ✅ |
| User CRUD                       | ✅ |
| Task create (local tasks)       | ✅ |

---

## API Endpoints (identical to original)

```
POST   /api/login
POST   /api/logout                        [auth]
GET    /api/me                            [auth]

GET    /api/users                         [auth]
POST   /api/users                         [auth]
GET    /api/users/{id}                    [auth]
DELETE /api/users/{id}                    [auth]

GET    /api/tasks                         [auth]
POST   /api/tasks                         [auth]
GET    /api/tasks/{id}                    [auth]
GET    /api/tasks/stats                   [auth]

GET    /api/reports/project-stats         (public)
GET    /api/reports/due-date-stats        (public)
GET    /api/reports/hubstaff              (public)

POST   /api/sync/trello                   [auth]
POST   /api/sync/mantis                   [auth]
POST   /api/sync/hubstaff                 [auth]
POST   /api/sync/all                      [auth]
POST   /api/sync/cron                     [auth]   # full Trello+Mantis+Hubstaff+AC run
GET    /api/sync/logs                     [auth]
GET    /api/sync/status                   [auth]   # last run time, per-source status

GET    /api/active-collab/teams-view      (public, DB-only)
GET    /api/active-collab/projects-view   (public, DB-only)
GET    /api/active-collab/managers-view   (public, DB-only)
GET    /api/active-collab/clients-view    (public, DB-only)
```

---

## Data flow

```
┌──────────────────┐        ┌──────────────────┐        ┌──────────────┐
│ cron */15 min    │───────▶│ bin/cron.php     │───┐    │ "Sync Data"  │
└──────────────────┘        └──────────────────┘   │    │ button (UI)  │
                                                   ▼    └──────┬───────┘
                                            ┌────────────────┐ │
                                            │ SyncRunner     │◀┘  POST /api/sync/cron
                                            │ ::runAll()     │
                                            └──────┬─────────┘
                                                   │
                                ┌──────────────────┼──────────────────┐
                                ▼                  ▼                  ▼
                          Trello/Mantis/      AC service      ac_sync_state +
                          Hubstaff services   syncAll()       integration_logs
                                │                  │
                                ▼                  ▼
                            tasks /          ac_users/companies/
                            task_user /      projects/tasks +
                            hubstaff_*       cache_store (4 view blobs)

Browser GET /api/active-collab/* and /api/reports/* read DB only.
```

