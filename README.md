# Pedago Academy ERP

Internal ERP for Pedago Academy. Its first module manages sales leads from the moment an inquiry arrives until the student's payment is verified.

> **Confidential.** This is a private, internal system. See [Confidentiality](#confidentiality) before you clone, share or deploy it.

## Purpose

- Give every inquiry to exactly one sales executive, quickly and fairly.
- Call the freshest leads first, because a lead cools with every hour it waits.
- Make every promised call-back visible, and stop new leads for anyone who lets one slip.
- Let managers move leads properly, with approvals and a record.
- Count an enrollment only when Accounts has verified the money.

## What it does

| Area | Features |
| :--- | :--- |
| People | Employees, roles, onboarding, teams, supervisors, shifts, date overrides, computed availability |
| Catalogue | Subjects, topics, courses, course batches with seats, campaigns |
| Lead intake | CSV upload, single entry, duplicate review |
| Distribution | Central queue, batch engine with hot/warm/cool/cold priority, manual assignment, on/off switch |
| Sales work | Ranked task list, lead page, call feedback, follow-ups, transfer requests |
| Control | Overdue and Lazy tracking, reassignment, transfers with team-leader approval and auto-approval |
| Finance | Payment verification, installment views |
| Insight | A dashboard per role, reports by executive, team and campaign |
| Administration | Settings, staff password read/reset with access log, in-app documentation and role guides |

Not built yet: the enrollment form, the customer payment page, payment gateways, receipts, notifications, and migration of the old Google Sheet data.

## Roles

| Role | Sees |
| :--- | :--- |
| Admin | Everything |
| Supervisor | The teams they supervise |
| Team Leader | Their own team |
| Sales Executive | Their own leads and tasks |
| Marketer (`MarketingExecutive`) | Campaigns, uploads, all leads |
| Accounts | Payments and installments |

Each role has a training guide inside the application under **My guide**; administrators see the full rule book under **Documentation**.

## Requirements

| Software | Version |
| :--- | :--- |
| PHP | 8.5, with the usual Laravel extensions (`pdo_mysql` or `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`) |
| Composer | 2 |
| Node.js and npm | Node 20 or newer |
| Database | MySQL 8 or MariaDB 10.6+ (SQLite works for a quick trial) |

Built with Laravel 13, Blade, Tailwind CSS 4 and Vite. Tests use Pest.

## Installation

```sh
git clone https://github.com/Md-Nur-A-Alam/erp-pedago-academy.git
cd erp-pedago-academy
composer install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_NAME="Pedago Academy ERP"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Dhaka

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

# First administrator, created by the seeder. Password: 6 to 12 characters.
INITIAL_ADMIN_PHONE=+8801XXXXXXXXX
INITIAL_ADMIN_PASSWORD=ChangeMe123

# Secret for reversible staff passwords. Generate once: php -r "echo bin2hex(random_bytes(24));"
STAFF_PASSWORD_KEY=
```

Then create the tables, the first administrator and the default settings, and build the front end:

```sh
php artisan migrate --seed
npm install
npm run build
```

## Running

```sh
composer run dev            # web server, queue, logs and Vite together
# or
php artisan serve           # web server only, after npm run build
```

Open `http://localhost:8000`, sign in with the administrator's phone number and password, change the password and complete the profile when asked.

For automatic lead distribution and transfer auto-approval the scheduler must run:

```sh
php artisan schedule:work                       # development
* * * * * php /path/to/artisan schedule:run     # production cron
```

## First steps after sign-in

1. **Settings** — check the batch size, priority windows and cut-off. Leave the distribution engine OFF for now.
2. **Courses** and **Campaigns** — create at least one of each.
3. **Employees** — create accounts. Each person changes their temporary password at first sign-in.
4. **Teams & roster** — create teams, set a team leader and a shift. An executive without a shift is always Off Duty and receives no leads.
5. **Upload leads** — upload a CSV for a campaign, then decide anything held in **Duplicate review**.
6. **Central queue** — press "Run distribution now", or turn the engine ON in the top bar.

## Useful commands

| Command | Purpose |
| :--- | :--- |
| `php artisan test --compact` | Run the test suite (SQLite in memory) |
| `vendor/bin/pint --dirty` | Format changed PHP files |
| `php artisan leads:distribute --force` | Run one distribution cycle now |
| `php artisan transfers:auto-approve` | Approve transfers that passed the waiting period |
| `npm run build` | Rebuild CSS and JavaScript after changing views |

## Documentation

| Where | What |
| :--- | :--- |
| In the application: **Documentation** / **My guide** | Live rules with current setting values; a training guide per role |
| `public/user_defined_assets/claude analysis/` | Requirements analysis, SRS, business logic, database design, weaknesses, roadmap, open questions, implementation update |
| `public/user_defined_assets/claude analysis/web/index.html` | The rule book as a web page, with a distribution calculator |
| `AGENTS.md` | Conventions for people and AI agents changing the code |

## Confidentiality

- This repository is the property of Pedago Academy and is for authorised staff and contractors only. Do not make it public, fork it publicly, or share its contents.
- **Never commit** `.env`, database dumps, customer lists, staff credentials or the staff password key.
- `STAFF_PASSWORD_KEY` lets staff passwords be read back. Keep it only in the server environment, back it up separately from the database, and never change it once accounts exist.
- Reading or resetting a staff password inside the application is recorded with the administrator's name and reason.
- Lead data is personal data. Export it only when the business needs it, and delete local copies afterwards.
- **Known issue:** `public/user_defined_assets/` currently contains working documents from the interim Google Sheet system, including real customer contacts and interim staff PINs, and that folder is served by the web server. Before any deployment: move it out of `public/`, change the interim PINs, and remove those files from the Git history.

## Status

Under active development. Current state and the order of remaining work are in `public/user_defined_assets/claude analysis/10_Implementation_Update.md`.
