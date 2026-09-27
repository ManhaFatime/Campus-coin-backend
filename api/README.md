# Campus Coin PHP API

Production-minded plain PHP + MySQL backend for the Campus Coin React/Vite frontend.

## What is included

- Student registration, login, logout and session validation
- Separate administrator login
- Password recovery/reset tokens
- Student profile and password changes
- Personal + system income/expense categories
- Income/expense CRUD with audit history
- Recurring transaction processing
- CSV transaction import
- Monthly category budgets and budget alerts
- Savings goals
- Personalized dashboard summary
- Monthly, six-month, daily/weekly and filtered reports
- Rule-based monthly insights and saving tips
- Bookmarking, pinning and dismissing
- Notifications
- Advisory category suggestion helper
- Admin dashboard, users, account status/reset, default categories, content and statistics
- Demo-account CLI seeder
- PowerShell smoke test

## Requirements

- PHP 8.1+ with PDO MySQL
- MySQL/MariaDB
- Apache/XAMPP locally
- Existing `campus_coin_db` created from the Campus Coin project SQL

## Installation in the current project

1. Put this whole `api` folder here:

   `C:\xampp\htdocs\campus-coin\api`

2. In phpMyAdmin, select `campus_coin_db` and import:

   `api/setup/001_extra_tables.sql`

   Run it once. It adds the small API additions needed for recurring entries and admin tip templates.

3. Create/update the two demo accounts from PowerShell:

   ```powershell
   C:\xampp\php\php.exe .\api\setup\seed-demo-users.php
   ```

4. Keep Apache and MySQL running.

5. Test the API directly:

   `http://localhost/campus-coin/api/health.php`

6. With the existing Vite proxy running, test:

   `http://localhost:8080/api/health.php`

7. Optional full smoke test:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\api\tests\smoke-test.ps1
   ```

## Local demo credentials

Student:
- Email: `student@campuscoin.test`
- Password: `Student@123`

Administrator:
- Email: `admin@campuscoin.test`
- Password: `Admin@123`

Change these before a public production deployment.

## Environment variables

The code works with XAMPP defaults without an `.env` package. For production, configure server environment variables when possible:

- `APP_ENV=production`
- `APP_NAME=Campus Coin`
- `APP_BASE_URL=https://your-domain.example`
- `APP_TIMEZONE=Asia/Karachi`
- `MAIL_FROM=no-reply@your-domain.example`
- `DB_HOST=...`
- `DB_PORT=3306`
- `DB_NAME=campus_coin_db`
- `DB_USER=...`
- `DB_PASS=...`

If environment variables are unavailable on the final host, update the fallback values in `config/database.php` and `config/app.php` before deployment.

## Important API endpoints

### Authentication
- `POST /api/auth/register.php`
- `POST /api/auth/login.php`
- `POST /api/auth/admin-login.php`
- `GET /api/auth/me.php`
- `POST /api/auth/logout.php`
- `POST /api/auth/forgot-password.php`
- `POST /api/auth/reset-password.php`

### Profile
- `GET /api/profile/get.php`
- `POST|PUT /api/profile/update.php`
- `POST /api/profile/change-password.php`

### Categories
- `GET /api/categories/list.php`
- `POST /api/categories/create.php`
- `POST|PUT /api/categories/update.php`
- `POST|DELETE /api/categories/delete.php`

### Transactions
- `GET /api/transactions/list.php`
- `POST /api/transactions/create.php`
- `POST|PUT /api/transactions/update.php`
- `POST|DELETE /api/transactions/delete.php`
- `GET /api/transactions/history.php`
- `POST /api/transactions/import-csv.php` (multipart form field: `file`)
- `POST /api/transactions/process-recurring.php`

### Dashboard / budgets / goals
- `GET /api/dashboard/summary.php`
- `GET /api/budgets/list.php?month=2026-09`
- `POST /api/budgets/upsert.php`
- `POST|DELETE /api/budgets/delete.php`
- `GET /api/goals/list.php`
- `POST /api/goals/create.php`
- `POST|PUT /api/goals/update.php`
- `POST|DELETE /api/goals/delete.php`

### Reports
- `GET /api/reports/monthly.php?month=2026-09`
- `GET /api/reports/six-months.php`
- `GET /api/reports/daily-weekly.php?month=2026-09`
- `GET /api/reports/custom.php?from=2026-09-01&to=2026-09-30&type=expense&category_id=6`

The React frontend can turn report data into a print/PDF or image export without adding a heavy PHP PDF dependency.

### Insights / tips / bookmarks
- `GET /api/insights/list.php`
- `POST /api/insights/generate.php`
- `GET /api/tips/list.php`
- `POST /api/tips/generate.php`
- `POST /api/tips/pin.php`
- `POST /api/tips/dismiss.php`
- `GET /api/bookmarks/list.php`
- `POST /api/bookmarks/toggle.php`

### Notifications
- `GET /api/notifications/list.php`
- `POST /api/notifications/mark-read.php`
- `POST /api/notifications/mark-all-read.php`

### Advisory categorization
- `POST /api/assistant/category-suggest.php`

This endpoint deliberately labels its result as advisory and currently uses transparent keyword rules. It can later be replaced with an AI provider without changing transaction storage.

### Admin
- `GET /api/admin/dashboard.php`
- `GET /api/admin/statistics.php`
- `GET /api/admin/users/list.php`
- `POST /api/admin/users/status.php`
- `POST /api/admin/users/reset-password.php`
- `GET /api/admin/categories/list.php`
- `POST /api/admin/categories/create.php`
- `POST|PUT /api/admin/categories/update.php`
- `POST|DELETE /api/admin/categories/delete.php`
- `GET /api/admin/content/list.php`
- `POST /api/admin/content/create.php`
- `POST|PUT /api/admin/content/update.php`
- `POST|DELETE /api/admin/content/delete.php`

## API response format

Success:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Invalid email or password.",
  "data": {}
}
```

## Frontend request rule

Use relative paths only:

```ts
fetch('/api/auth/login.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password }),
});
```

This works through the Vite proxy locally and on the same domain after deployment.

## Security notes

- Passwords use PHP `password_hash()` / `password_verify()`.
- Queries use PDO prepared statements.
- Sessions use HttpOnly and SameSite cookies.
- Student endpoints always scope records to the authenticated user.
- Admin endpoints require an administrator session.
- Deleted/edited transaction details are retained in the audit table.
- Config/helper/setup directories are protected from normal web access by `.htaccess`.
- Do not expose PHP error output in production; use server logs instead.

## Password reset email

In local mode a reset token is returned in the response and the reset URL is written to the PHP error log for development/testing. In production the helper uses PHP's `mail()` function; actual delivery therefore depends on the final host's mail configuration.
