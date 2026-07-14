# Budgeting and Expense Management System (PHP + MySQL)

A complete, working web application implementing the full spec: role-based
access, budget allocation settings, denomination tracking, income & expense
entry with a 4-level expense approval workflow, asset register, and full
reporting suite (Budget Allocation, Expense, Income Statement, Balance Sheet,
Cash Flow) plus a KPI/chart dashboard.

## Requirements
- PHP 8.0+ (uses PDO, password_hash)
- MySQL 5.7+ / MariaDB 10.3+ (uses generated columns)
- A web server (Apache/Nginx) or PHP's built-in server for quick testing

No external PHP packages are required — everything is plain PHP with
Bootstrap 5 / Bootstrap Icons / Chart.js loaded from CDN in the browser.

## Fixes applied in this build

This package had a few gaps that are corrected here:

- **Missing `tools/setup_passwords.php`** — the README and `sql/schema.sql`
  both referenced this one-time script to activate the default `admin` /
  `treasurer` logins, but it wasn't actually included in the download. It's
  now added (see step 3 below).
- **`config/config.php`** now reads `DB_HOST`, `DB_PORT`, `DB_NAME`,
  `DB_USER`, `DB_PASS`, `BASE_URL`, and `APP_ENV` from environment variables
  first, falling back to the original local defaults. This is required for
  any host (Vercel, Railway, Render, etc.) where you set config through a
  dashboard instead of editing the file directly, and it stops raw database
  error messages from being shown to visitors when `APP_ENV=production`.
- Closed a stray unclosed `<small>` tag on the login page footer.
- Corrected a comment in `sql/schema.sql` that pointed to a script name that
  never existed in the package.

## 1. Import the database

```bash
mysql -u root -p < sql/schema.sql
```

This creates the `budget_system` database, all tables, and seed data
(default budget allocation percentages, 4 approver placeholders, treasurer
profile, bill denominations, roles, and two default user accounts).

## 2. Configure the database connection

Edit `config/config.php` and set:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'budget_system');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

If you're hosting the app in a sub-folder (e.g. `http://localhost/budget-system/`),
update `BASE_URL` accordingly (e.g. `'/budget-system/'`).

## 3. Set default passwords (required — one-time step)

The schema ships with placeholder password hashes that will **not** work.
Run this once, either via CLI or by visiting it in your browser:

```bash
php tools/setup_passwords.php
```

This sets:
- `admin` / `admin123` → Super Admin
- `treasurer` / `treasurer123` → Treasurer

**Delete `tools/setup_passwords.php` after running it once**, for security.

## 4. Run

Quick local test with PHP's built-in server (from the project root):

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/login.php`.

For production, point your Apache/Nginx document root at this folder and
make sure `config/`, `includes/`, `sql/`, and `tools/` are not web-accessible
if your host doesn't already restrict dotfiles/PHP includes (optional
hardening — the app works fine as-is on a single-vhost setup).

## Roles & Permissions

| Role | Access |
|---|---|
| Super Admin | Everything: Settings, Users, Income, Expenses, Assets, Reports |
| Treasurer | Income, Expenses, Denomination, Assets, Reports |
| Approver 1–3 | Approve Expenses queue (their level only) |
| Approver 4 | Approve Expenses queue — final approval |

Approvers and Treasurer contact-info records (under Settings) are separate
from login accounts — assign a login account with the matching role
(`approver_1`...`approver_4`, `treasurer`) under **Settings → Users** so
the right person can act in the approval queue.

## Expense Approval Workflow

1. Treasurer records an expense → status `Pending`.
2. Approver 1 approves → `Approved by L1`. Approver 2 approves → `Approved by L2`.
3. Approver 3 approves → `Approved by L3`. Approver 4 approves → `Approved` (final).
4. Any approver can reject at their stage → status `Rejected` (end of workflow).
5. Only `Approved` expenses count toward reports, dashboard KPIs, and budget
   utilization.

Super Admin can act at any approval level from the same **Approve Expenses**
screen (useful for catching up on backlog or covering for an absent approver).

## Module Map

```
index.php                     Dashboard (KPIs + Chart.js charts)
login.php / logout.php         Authentication
settings/budget_allocation.php Budget % settings (must total ≤100%)
settings/approvers.php         4 approver contact profiles
settings/treasurer.php         Treasurer contact profile
settings/users.php              User accounts & roles
denomination/index.php          Bill denomination entry + cash summary + allocation report
income/index.php                Income entry + weekly/monthly/quarterly/yearly report
expenses/index.php               Expense entry + weekly/monthly/quarterly/yearly report
expenses/approve.php             4-level approval queue
assets_module/index.php          Asset register (CRUD)
reports/budget_allocation.php    Allocation vs spend vs remaining
reports/expense_report.php       Filterable expense report (category, date range)
reports/income_statement.php     Income - Expenses = Net Income
reports/balance_sheet.php         Assets / Liabilities / Equity (+manage bank/loan/payable)
reports/cash_flow.php             Operating/Investing/Financing + beginning/ending cash
```

## Notes on data model choices

- **Fund Source** on an expense links loosely (by name) to a Budget
  Allocation item, so the Budget Allocation Report can show spend vs.
  remaining per category.
- **Cash on hand** and the Cash Flow Statement's cash figures are derived
  from recorded **Denomination** entries (the physical bill count), matching
  the spec's cash-management workflow.
- **Bank balance, loans, and payables** are manually recorded ledger lines
  (Settings-like mini-CRUD embedded directly in the Balance Sheet report)
  since the spec doesn't define a dedicated bank/loan module.
- Passwords are stored with PHP's `password_hash()` (bcrypt) — never in
  plain text.
- All forms are CSRF-protected and all SQL uses PDO prepared statements.

## Pushing to GitHub

```bash
git init
git add .
git commit -m "Initial commit: budgeting and expense management system"
git branch -M main
git remote add origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```

`.gitignore` already excludes `.env`, `.vercel`, editor files, etc.

## Deploying — read this first

This is a classic **PHP + MySQL** app: every page is a server-rendered
`.php` file, login state is a PHP session, and it expects a real MySQL
server to talk to over PDO. **Vercel's platform is built around Node.js /
static frontends / serverless functions — it has no official PHP support,
and no built-in database.** It's not a case of "just enable a setting";
this class of app runs outside what Vercel is designed for.

### Recommended: a PHP-friendly host (works with this code as-is)
Push the repo to GitHub, then connect/deploy from GitHub on any of:
- **Railway** or **Render** — install PHP via a small Dockerfile/buildpack, add a MySQL plugin, set the `DB_*` env vars from `.env.example`.
- **Hostinger, InfinityFree, traditional cPanel hosting** — upload the files, import `sql/schema.sql` via phpMyAdmin, edit `config/config.php` (or set env vars if supported) — this matches the original local setup almost exactly.
- **DigitalOcean App Platform** — has native PHP + managed MySQL support.

These all give you real persistent sessions and a real MySQL connection, which this app needs to work reliably.

### If you still want to try Vercel (experimental)
A community **`vercel-php`** runtime can run PHP files as serverless functions, and this package includes a ready `vercel.json` for it. To use it:

1. **Provision MySQL somewhere else** — Vercel doesn't host databases. Use a service like PlanetScale-style MySQL, Railway MySQL, or Aiven, and import `sql/schema.sql` into it.
2. In the Vercel project's **Settings → Environment Variables**, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL=/`, `APP_ENV=production` (matching `.env.example`).
3. Run `tools/setup_passwords.php` locally with those same env vars set, pointed at the remote database, to activate the `admin`/`treasurer` logins — the tool is deliberately excluded from the deployed bundle (see `.vercelignore`) so it's never web-accessible in production.
4. Import the GitHub repo in Vercel and deploy; `vercel.json` wires up the PHP runtime and blocks direct access to `config/`, `includes/`, `sql/`, and `tools/`.
5. **Known limitation:** PHP's default file-based sessions rely on a persistent local disk. Vercel functions are stateless and short-lived, so login sessions can behave unreliably (people may get logged out unexpectedly) unless you swap the session handler for something external (e.g. a Redis-backed session store). This isn't fixed in this package — it's a structural mismatch between session-based PHP apps and serverless platforms, not a simple bug.

If reliability matters (this is a real accounting/expense-approval tool), the PHP-friendly hosts above are the safer choice; treat Vercel as a proof-of-concept option.
