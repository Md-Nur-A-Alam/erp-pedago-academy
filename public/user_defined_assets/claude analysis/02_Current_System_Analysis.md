# 02 — Current System Analysis

What the Laravel project contains at commit `85ef9a8`, module by module, and how far it is from the specification.

---

<!-- build-update -->

## Build update — 17 November 2026

This document describes commit `85ef9a8`. The system has since grown; the as-built picture is now:

| Area | Then | Now |
| :--- | :--- | :--- |
| Routes | 62 | 82 |
| Automated tests | 154 | 227, all passing |
| Controllers | 16 | 25 |
| Action classes | 3 | 9 (distribution, feedback, transfer decision, payment review, password cipher and hasher) |
| Migrations | 21 | 24 (roster cut-off and holiday; transfer decision columns; password access log) |
| Blade components | none | `x-ui.*`: card, stat, pill, tabs, button, dialog, icon, empty |
| Scheduler | empty | `leads:distribute` every minute, `transfers:auto-approve` hourly |

New screens: central queue, follow-ups, transfers, payments review, installments, reports, staff passwords, documentation/guide, my tasks, lead workspace, and a dashboard per role. The module-by-module detail is in document 10.

<!-- build-update -->


## 1. Technology and structure

| Item | Value |
| :--- | :--- |
| Framework | Laravel `^13.17`, PHP 8.5 (`composer.json` requires `^8.3`) |
| Front end | Blade templates, Tailwind CSS v4 through Vite 8. No JavaScript framework; small inline scripts and native `<dialog>` elements |
| Database | MySQL in development (`DB_DATABASE=ERP_Pedago_Acagemy`); SQLite in-memory for tests |
| Testing | Pest 5 — 154 tests, 791 assertions, all passing |
| Dev tooling | Laravel Boost, Pint, Pail, Tinker |
| Session / cache / queue | All on the database driver |
| Timezone | `UTC` (`config/app.php:68`) |
| Packages for auth or permissions | None — no Breeze, Sanctum, or role package |

```
app/
├── Actions/            CreateEnrollmentFromLead, ImportLeadsFromCsv, ReviewEmployeeChangeRequest
├── Http/
│   ├── Controllers/    14 controllers + Auth/ (2)
│   └── Middleware/     EnsureEmployeeOnboardingComplete
├── Models/             24 models
├── Providers/          AppServiceProvider (login rate limiter only)
└── Rules/              PhoneNumber
database/migrations/    21 files (3 framework + 18 project)
resources/views/        25 Blade files, about 5,700 lines
routes/web.php          62 routes, one auth group
routes/console.php      no scheduled tasks
tests/Feature/          16 files + Auth/ + Models/
```

**Not present:** `app/Policies`, `app/Modules`, form request classes, PHP enums, jobs, events, listeners, notifications, mail classes, console commands, API routes, Blade components, language files.

---

## 2. Database as built

25 project tables. Row counts are from the local development database.

### 2.1 People

| Table | Key columns | Rows | Notes |
| :--- | :--- | --: | :--- |
| `users` | phone_number (unique, nullable), password (bcrypt), must_change_password, remember_token | 12 | Login only. **5 rows have no employee** |
| `employees` | user_id (unique), office_id (unique), name, designation, joining_date, role enum(6), status enum(Active, Inactive, Suspended), shift_id, team_id, edited_at, edited_by | 7 | 1 Admin, 2 Team Leaders, 4 Sales Executives |
| `employee_details` | employee_id (unique), email (unique), secondary and emergency contacts, dob, nid_number (encrypted), blood_group, addresses | — | Six fields are mandatory for onboarding |
| `employee_change_requests` | employee_id, requested_by, requested_changes JSON, reason, status, reviewer fields | 0 | |
| `shifts` | shift_start_time, shift_end_time, working_hours, is_active | 1 | No name, no working days |
| `employee_rosters` | employee_id, shift_id (nullable), roster_date, is_on_duty, availability_status enum(6), last_active_at; unique(employee, date) | 0 | Used as per-date overrides |
| `teams` | name, team_leader_id (unique), shift_id, is_active, edited_at, edited_by | 2 | |
| `supervisor_teams` | supervisor_id, team_id, unique pair | — | Many-to-many |

### 2.2 Catalogue and campaigns

| Table | Key columns | Rows | Notes |
| :--- | :--- | --: | :--- |
| `subjects` | name (unique) | 3 | |
| `course_topics` | subject_id, name; unique(subject, name) | 5 | |
| `courses` | title, code (unique), description, conditions, subject_id, course_topic_id (composite FK), version enum, category, base_fee, is_active | 5 | |
| `course_batch` | course_id, batch_number, batch_name, short_code, capacity, is_latest, start_date | 0 | Singular table name |
| `leads_source` | source_id PK, channel enum(11), campaign_name, course_id, distribution_mode enum(Auto, Manual), is_active, created_by | 6 | Holds both channels and campaigns |

### 2.3 Leads and sales

| Table | Key columns | Rows | Logic exists? |
| :--- | :--- | --: | :-- |
| `leads` | source_id, student_name, guardian_name, contact_number, whatsapp_number, alternative_contact_number, email, location, age, class, student_parent_type, student_background, ad_set_id, interested_course_id, status enum(11), current_owner_id, previous_owner_id, assigned_by_employee_id, assigned_at, last_action_at, team_id | 0 | Create, list, assign |
| `duplicate_lead_reviews` | incoming_lead_data JSON, matched_lead_id, match_reason, status enum(4), reviewer fields | 0 | Yes |
| `lead_batches` | employee_id, assigned_by_employee_id, batch_size, status, assigned_at, completed_at | 0 | **No** |
| `lead_batch_items` | batch_id, lead_id, is_completed, action_taken, completed_at | 0 | **No** |
| `lead_feedbacks` | lead_id, employee_id, call_status, customer_interest, customer_concern, sales_note, next_action, action_date | 0 | **No** |
| `lead_followups` | lead_id, employee_id, followup_date_time, note, is_done, overdue_alert_sent | 0 | **No** (read by counters only) |
| `lead_transfers` | lead, from/to employee and team, reason, both TL approval fields, is_auto_transferred, is_admin_override, status, transferred_at | 0 | **No** |

### 2.4 Enrollment and finance

| Table | Key columns | Rows | Logic exists? |
| :--- | :--- | --: | :-- |
| `enrollments` | lead_id (unique), course_id, course_batch_id (composite FK), sales_executive_id, secure_uuid, student and guardian fields, base_course_fee, discount_percentage, final_deal_amount, total_installments, status enum(4) | 0 | Action only, no route |
| `installment_schedules` | enrollment_id, installment_number, due_amount, due_date, followup_generated, status enum(4), paid_at | 0 | **No** |
| `payments` | enrollment_id, installment_schedule_id, amount, payment_date, payment_method enum(4), transaction_id, slip_screenshot_path, verifier, status, rejection_reason, verified_date | 0 | **No** |
| `system_settings` | key (unique), value, description | 2 | `lead_batch_size`, `assignment_cutoff_minutes` |

### 2.5 Schema observations

- No database triggers exist, although `db.md` §4 describes immutability triggers as "enforced".
- `lead_batch_items` has no unique key on (batch_id, lead_id); v-03 specifies one.
- `leads` has single-column indexes on contact_number, alternative_contact_number and status. `whatsapp_number` and `current_owner_id` rely on FK or no index. The composite indexes in v-03 are absent.
- Timestamp conventions vary: `edited_at` + `edited_by` on five tables, standard `updated_at` on others, `created_at` only on some.
- The `MarketingExecutive` role is added by a raw MySQL `ALTER TABLE` that is skipped on other drivers, so the test database and production differ.
- The 5 orphan `users` rows show nothing prevents a login account without an employee.

---

## 3. Module-by-module analysis

### 3.1 Authentication and onboarding — *Built*

| File | Behaviour |
| :--- | :--- |
| `Auth/AuthenticatedSessionController` | Login by normalised phone and password. Rate limit 5 per minute per phone+IP. Rejects accounts whose employee is not Active. Redirects to forced password change, then profile completion, then dashboard |
| `Auth/PasswordController` | Change password with current password. Length 6–12 |
| `EnsureEmployeeOnboardingComplete` | Blocks every page until the temporary password is changed and six profile fields are filled |
| `DatabaseSeeder` | Creates the first Admin from `INITIAL_ADMIN_*` environment values |

Gaps: no forgot-password or admin reset; account status is checked only at login (W-SEC-05); password length capped at 12 (W-SEC-06).

### 3.2 Employees and profile — *Built*

| File | Behaviour |
| :--- | :--- |
| `EmployeeController` | Admin only. Directory with search, role, status and availability filters and sortable columns. Create with auto office ID `PED-nnn` and temporary password. Per-date duty override: on/off duty, shift, status |
| `EmployeeProfileController` | Self-service profile. Personal fields save immediately. Name, office ID, designation, joining date, role, status and login phone create a change request unless the actor is Admin |
| `EmployeeChangeRequestController` + `ReviewEmployeeChangeRequest` | A higher role approves or rejects. Row locks prevent double review |

Authority levels: Admin 4, Supervisor 3, Team Leader 2, Sales Executive / Accounts / MarketingExecutive 1.

Gaps: role and status are self-requestable (W-SEC-03); the last admin can demote themselves (W-SEC-08); deactivating an employee does nothing to their leads.

### 3.3 Teams, shifts and availability — *Partial*

| File | Behaviour |
| :--- | :--- |
| `TeamController` | Index with KPIs and a 30-day conversion comparison; team page with members, pipeline and per-member stats; create, edit, delete, toggle active, remove member. A team has one leader (unique), several supervisors, members through `employees.team_id`, and one primary shift |
| `Employee::effectiveShift()` | Date override → team shift → employee default shift |
| `Employee::effectiveAvailabilityStatus()` | Override status → otherwise "Available" if any shift exists |

Gaps: no shift management screen — shifts are created implicitly from typed times; no weekly off days; availability ignores the clock, the cut-off and batch state; three different availability calculations exist (W-BUG-08); no scoping by role (W-SEC-04); the team "active" flag affects nothing.

### 3.4 Course catalogue — *Built*

`SubjectController`, `CourseTopicController`, `CourseController`, `CourseBatchController` — Admin only. Subject → topic → course hierarchy with a composite foreign key that stops a course pairing a topic with the wrong subject. Batches have a number, name, short code, capacity, start date and a single "latest" flag per course. Deletion is blocked when referenced. Row locks are used throughout. This is the most complete module.

Gaps: capacity can be reduced below the seats already reserved (W-BUG-11).

### 3.5 Campaigns — *Built*

`CampaignController` — Admin and Marketing Executive. A campaign is a `leads_source` row with a name; it needs a channel, a course and a distribution mode. Unique per channel. Filters, sort by lead count, statistics. Deletion blocked while leads exist.

Gaps: the lead screens create nameless or course-less `leads_source` rows through `firstOrCreate`, bypassing these rules (W-BUG-12); `distribution_mode` and `is_active` are stored but nothing reads them.

### 3.6 Lead ingestion — *Partial*

| Entry path | File | Who | Duplicate check |
| :--- | :--- | :--- | :--- |
| Bulk CSV | `LeadUploadController::store` + `ImportLeadsFromCsv` | Admin, Marketing | Exact primary phone |
| Single (upload page) | `LeadUploadController::storeSingle` | Admin, Marketing | Exact primary phone |
| Quick create (leads page) | `LeadController::store` | Admin, Marketing, Team Leader, Supervisor | Exact primary phone — **crashes on match** (W-BUG-01) |
| Approve from review | `DuplicateLeadReviewController::update` | Admin | None |

The CSV import reads up to 500 rows, accepts a selectable set of 12 columns, strips a `p:` prefix from phones, converts Bangla digits in ages, validates every row, and runs in one transaction.

Gaps: four separate creation paths with different rules; one invalid row rejects the file; CSV only; no cross-number matching; no automatic suppression of exact duplicates; phone formats are not canonical (W-BUG-02); imported leads do not inherit the campaign's course (W-BUG-05); no lead enters any distribution process.

### 3.7 Duplicate review — *Partial*

`DuplicateLeadReviewController` — Admin only. Lists pending reviews with search. Three decisions: approve as new lead, merge (fills only blank fields of the matched lead), discard. Locked against double processing.

Gaps: the spec's fourth action (edit and dispatch) is missing; supervisors cannot review; a merge discards the fact that a second inquiry happened; an approved lead is not sent to distribution.

### 3.8 All leads — *Partial*

`LeadController` — index with five KPI tiles, twelve filters, sorting, pagination, a details dialog, CSV export, and direct assignment of a lead to an employee.

Gaps: every role with access sees every lead (W-SEC-02); assignment overwrites ownership with no history and no batch (W-BUG-04); there is no lead page, no edit, no timeline.

### 3.9 Dashboard — *Partial*

`DashboardController` — Admin gets seven metrics, a pipeline and today's roster, filtered by a date range. Every other role gets a welcome panel with links.

Gaps: queue metrics are wrongly limited by the date range (W-BUG-07); no dashboards for Supervisor, Team Leader, Sales Executive or Accounts.

### 3.10 Settings — *Partial*

`SystemSettingController` — Admin edits `lead_batch_size` (1–50) and `assignment_cutoff_minutes` (0–180). Neither value is read anywhere else in the code.

Gaps: four of the six settings in v-03 are missing, including the engine switch; key names differ from v-03.

### 3.11 Enrollment — *Schema plus one action*

`CreateEnrollmentFromLead` locks the course, the lead and the batches, picks the lowest-numbered batch with free seats, creates the enrollment with a UUID, and marks the lead Converted — in one transaction with retries. No route, controller or screen calls it.

Gaps: W-BUG-10 lists five rule mismatches.

### 3.12 Everything else — *Not built*

Distribution engine · batches · sales task screen · feedback · follow-ups · overdue and Lazy · transfers · reassignment queue · customer portal · payments · accounts dashboard · installments and T−2 · receipts · notifications · reports and KPIs · scheduler · audit log · API.

---

## 4. Views

| View | Lines | Purpose |
| :--- | --: | :--- |
| `layouts/app` | 235 | Sidebar and mobile navigation, role-based links, flash messages |
| `auth/login`, `auth/change-password` | 101 | Follow the login mockup |
| `dashboard` | 204 | Admin metrics; simple panel for others |
| `employees/index`, `create`, `profile` | 649 | Directory, create, profile with duty overrides |
| `teams/index`, `show`, `create`, `edit` | 1,634 | Team management |
| `campaigns/index`, `edit` | 717 | Campaign list with create dialog |
| `courses/*`, `subjects/index`, `course-batches/*` | 631 | Catalogue |
| `leads/index`, `upload` | 1,154 | All leads and upload |
| `duplicate-lead-reviews/index`, `employee-change-requests/index`, `settings/index` | 195 | Queues and settings |
| `welcome` | 223 | Unused Laravel default page |

Observations: no reusable Blade components although v-02 lists them, so tables, pills and dialogs are repeated; no raw `{!! !!}` output, so escaping is safe by default; the interface is English only; no Bengali font is loaded, so Bangla names fall back to a system font.

---

## 5. Tests

The table counts test definitions per area (133 in total). Data sets expand them to the 154 tests that run.

| Area | Definitions | Comment |
| :--- | --: | :--- |
| Login and onboarding | 25 | Thorough on phone formats and password length |
| Lead upload | 17 | Good on parsing and validation |
| Campaigns | 15 | Good |
| Course, subject, batch | 15 | Good, including the composite key |
| Teams and duty time | 15 | Happy paths; no test of role scoping |
| Model relationships | 11 | One asserts that passwords are one-way hashes |
| All leads | 10 | **One asserts that a Sales Executive may open the all-leads page** |
| Dashboard and settings | 9 | |
| Duplicate review | 6 | Good |
| Enrollment action | 4 | Seat allocation only |
| Employee directory | 4 | |
| Framework examples | 2 | |

Observations:

- The suite passes while eight real defects exist, because no test covers them and one test encodes the wrong behaviour.
- Tests run on SQLite, production on MySQL. MySQL strict mode, enum behaviour, the raw role `ALTER`, composite keys and any future trigger are not exercised as they will run.
- Nothing is tested for the engine, because nothing of the engine exists.

---

## 6. Implementation status against the specification

Counts refer to the functional requirements in document 03.

| Module | Requirements | Built | Partial | Schema only | Not built |
| :--- | --: | --: | --: | --: | --: |
| M1 Authentication and onboarding | 9 | 6 | 1 | 0 | 2 |
| M2 Employees, roles, permissions | 10 | 5 | 2 | 0 | 3 |
| M3 Teams, shifts, availability | 11 | 3 | 4 | 0 | 4 |
| M4 Course catalogue | 6 | 6 | 0 | 0 | 0 |
| M5 Campaigns | 5 | 3 | 1 | 0 | 1 |
| M6 Lead ingestion | 10 | 2 | 4 | 0 | 4 |
| M7 Duplicates | 8 | 2 | 2 | 0 | 4 |
| M8 Distribution engine | 12 | 0 | 1 | 2 | 9 |
| M9 Sales workspace | 12 | 0 | 0 | 2 | 10 |
| M10 Overdue, Lazy, reassignment | 8 | 0 | 1 | 0 | 7 |
| M11 Transfers | 9 | 0 | 0 | 1 | 8 |
| M12 Conversion and enrollment | 9 | 0 | 2 | 1 | 6 |
| M13 Customer portal and payments | 9 | 0 | 0 | 1 | 8 |
| M14 Accounts and installments | 8 | 0 | 0 | 1 | 7 |
| M15 Notifications | 4 | 0 | 0 | 0 | 4 |
| M16 Dashboards and reports | 8 | 0 | 3 | 0 | 5 |
| M17 Settings and administration | 5 | 1 | 1 | 0 | 3 |
| M18 History and audit | 5 | 0 | 1 | 0 | 4 |
| M19 Data migration | 4 | 0 | 0 | 0 | 4 |
| **Total** | **152** | **28** | **23** | **8** | **93** |

28 of 152 requirements are built (18%) and 23 more are partly built (15%). The built part is concentrated in setup modules (M1–M5). The modules that deliver the product's purpose (M8–M14) have no working requirement.

### 6.1 The v-03 acceptance checklist, checked against the code

| # | Criterion | v-03 mark | In the code |
| :-- | :--- | :-- | :--- |
| 1 | Master engine toggle | Done | Not built — no setting, no engine |
| 2 | Batch and scarcity distribution | Done | Not built |
| 3 | Dynamic shifts with 30-minute cut-off | Done | Shifts stored; cut-off never applied |
| 4 | Lazy penalty | Done | Not built — the value can only be typed by hand in a duty override |
| 5 | Follow-up isolation | Done | Not built |
| 6 | End-to-end conversion | Open | Action exists; no route, link or payment |
| 7 | Dual gateway and manual payments | Done | Not built |
| 8 | Dual-approved transfers | Done | Not built |
| 9 | Protected in-context search | Done | Not built — and the opposite is true (W-SEC-02) |
| 10 | Immutable history | Done | Not built — no triggers, no guards, nothing writes history |

---

## 7. Strengths worth keeping

- Consistent use of database transactions and row locks where records are changed concurrently (change requests, duplicate review, course and batch edits, enrollment).
- Composite foreign keys that make an invalid subject/topic or course/batch pairing impossible.
- Server-side validation on every form, with a shared phone rule.
- The onboarding gate (forced password change, then mandatory profile) works and is well tested.
- National ID numbers are encrypted at rest and hidden from serialisation.
- Typed method signatures and return types throughout; the code follows the project's own rules in `AGENTS.md`.
- Seat reservation is derived from enrollments rather than stored, so it cannot drift.
- Blade output is escaped everywhere.
- A real test suite with factories for every model, which makes the missing tests cheap to add.


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

The interim script now exists in two versions. `Code-2.txt` adds to `Code.txt`: guardian name and a lead-level remark, per-rep Auto or Manual mode with a daily cap counted by day, and a WhatsApp thank-you logged as a note.

How each interim feature maps to the application:

| Interim script | In the application |
| :--- | :--- |
| Sheets Staff, PIN login, IsOnline | Employees with hashed staff passwords; availability comes from shifts, roster and leave, not a manual online flag |
| Batch of 3 topped up on login and after feedback | Distribution engine cycle every minute, scarcity rule, batch size setting |
| Least-loaded-first pick | Longest-idle-first, with temperature priority |
| AssignMode and DailyCap on Staff | `employees.assign_mode`, `daily_lead_cap`, `auto_count_date`, `auto_count_today`; page Assignment controls |
| Priority High, Medium, Low | `leads.priority`, set on the feedback form, tie-break in Call next |
| SecondaryPhones | Table `lead_phones` and the other number fields; any finds the lead |
| Inquiries sheet and reactivation | Table `lead_inquiries`; closed lead reopens to its previous owner; open lead gets a follow-up on a new day |
| Activities with Note rows | Calls in `lead_feedbacks`; non-call history in `lead_events` |
| lookupLeadByPhone | Number check page |
| bulkImportLeads (paste) | Paste leads page, with per-line skip reasons |
| mergeDuplicateContacts | Merge duplicates page |
| diagnose and fix functions | Data health page |
| WhatsAppThanksMsg | Setting `whatsapp_thanks_message` and the Thank on WhatsApp button |
| GuardianName, Remarks | `leads.guardian_name`, `leads.remarks` |

Not carried over because a database makes them unnecessary: phone and settings text-format repairs, the Number-to-text conversion bug, and the single spreadsheet row limit.
