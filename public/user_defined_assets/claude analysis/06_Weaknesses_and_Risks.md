# 06 — Weaknesses and Risks

Every defect and weakness found in the project, with evidence and a fix.

**Severity** — *Critical*: data exposure or loss of control, fix before any further use. *High*: wrong behaviour that users will hit or that breaks a core rule. *Medium*: will cause problems as use grows. *Low*: quality or hygiene.

**Verified** means the behaviour was reproduced by running the application against a test database on 16 November 2026. The probe was temporary and has been removed; nothing in the project was changed.

---

<!-- build-update -->

## Build update — 17 November 2026

| Weakness | Status | What was done |
| :--- | :--- | :--- |
| W-SEC-01 Data, PINs and key document in `public/` and on GitHub | **Open — act first** | Nothing; it needs the owner (rotate PINs, move the folder, purge history) |
| W-SEC-02 Every role sees every lead | Fixed | List, export and lead page are scoped by role, with tests |
| W-SEC-03 Self-requested role change | Fixed | Role and status are dropped from self-service requests |
| W-SEC-04 Any team leader can change any team | Fixed | Leaders and supervisors reach only their own teams; create, delete and deactivate are admin-only |
| W-SEC-05 Suspended employee stays signed in | Fixed | Status is checked on every request and the session ended |
| W-SEC-06 No password reset | Fixed for admins | Admin reset to a temporary password; self-service reset still absent |
| W-SEC-07 Reversible passwords | Implemented as decided | The exposure remains: anyone holding the database and the key can read every password |
| W-SEC-08 Last admin can be removed | Fixed | Blocked at model level |
| W-BUG-01 Quick-create duplicate returns a server error | Fixed | The duplicate goes to review |
| W-BUG-02 Phone formats stored as different people | Open | — |
| W-BUG-04 Assignment leaves no history | Partly fixed | Previous owner recorded, batch created; no activity timeline yet |
| W-BUG-05 Imported leads get no course | Open | — |
| W-BUG-07 Queue figures limited by date range | Fixed | Live figures ignore the range |
| W-BUG-09 Time zone UTC | Fixed | Asia/Dhaka |
| W-ARCH, W-PERF, W-OPS items | Mostly open | Rules moved into action classes for the new modules; enums, policies and history table not done |
| W-QA-01 No tests for the defects | Fixed for the repaired ones | `RepairedDefectsTest`, `RolePanelsTest` |
| W-UX No shared components | Fixed for new screens | `x-ui.*` components; older screens were re-coloured, not rebuilt |

New risks introduced by the build:

| Risk | Effect | Mitigation |
| :--- | :--- | :--- |
| The scheduler is not running on the server | No automatic distribution or transfer auto-approval | Run `php artisan schedule:work` or a cron entry; the manual button still works |
| `STAFF_PASSWORD_KEY` is lost or changed | No stored password can be verified or read | Back the key up separately from the database; never rotate it without re-setting every password |
| Follow-ups, transfers and payments screens depend on data the enrollment form would create | Finance screens stay empty | Build the enrollment form next |

<!-- build-update -->


## 1. Summary

| ID | Title | Severity | Verified |
| :-- | :--- | :-- | :-- |
| W-SEC-01 | Customer data, staff PINs and the cipher key are web-served and in the Git repository | Critical | Yes |
| W-SEC-02 | Sales executives can view and export all leads | Critical | Yes |
| W-SEC-03 | Self-service role change lets a sales executive become Admin | Critical | Yes |
| W-SEC-04 | Team leaders and supervisors can alter or delete any team | High | Yes |
| W-SEC-05 | Suspended and inactive employees keep their access | High | Yes |
| W-SEC-06 | Weak password rules and no password recovery | High | Read |
| W-SEC-07 | The requested reversible password scheme is breakable | High | Analysis |
| W-SEC-08 | The last admin can remove their own admin role | Medium | Yes |
| W-SEC-09 | No central authorisation; role checks are scattered | Medium | Read |
| W-SEC-10 | No audit trail for sensitive actions | Medium | Read |
| W-SEC-11 | Unrestricted lead export, open to formula injection | Medium | Read |
| W-SEC-12 | Development settings unsafe for production | Low | Read |
| W-BUG-01 | Quick-create crashes when the phone already exists | High | Yes |
| W-BUG-02 | Phone numbers are not canonical; duplicates pass | High | Yes |
| W-BUG-03 | Duplicate detection is narrow and implemented three times | High | Read |
| W-BUG-04 | Manual assignment overwrites ownership without a trace | Medium | Yes |
| W-BUG-05 | Imported leads do not take the campaign's course | Medium | Read |
| W-BUG-06 | Bulk import is all-or-nothing, CSV only, 500 rows | Medium | Read |
| W-BUG-07 | Dashboard queue figures are hidden by the date range | Medium | Read |
| W-BUG-08 | Availability is computed three different ways | Medium | Read |
| W-BUG-09 | Application time zone is UTC | Medium | Read |
| W-BUG-10 | The enrollment action disagrees with the specification | Medium | Read |
| W-BUG-11 | Batch capacity can be set below reserved seats | Low | Read |
| W-BUG-12 | Lead screens create campaigns that break campaign rules | Low | Read |
| W-BUG-13 | Removing or deactivating people and teams leaves leads stranded | Medium | Read |
| W-ARCH-01 | Core engine absent; tables without logic | High | Read |
| W-ARCH-02 | Large controllers, repeated code, no services or policies | Medium | Read |
| W-ARCH-03 | Statuses and roles are loose strings | Medium | Read |
| W-ARCH-04 | No lead history is recorded | High | Read |
| W-ARCH-05 | Shifts have no management, names or working days | Medium | Read |
| W-PERF-01 | Query counts grow with teams and members | Medium | Read |
| W-PERF-02 | Missing indexes and unindexed searches | Low | Read |
| W-PERF-03 | Office ID generation can collide | Low | Read |
| W-QA-01 | Tests pass while defects exist; one test asserts a defect | Medium | Yes |
| W-QA-02 | Tests run on SQLite, production on MySQL | Medium | Read |
| W-OPS-01 | No scheduler, queue worker, backup or deployment notes | Medium | Read |
| W-OPS-02 | Documentation is outdated or misleading | Medium | Read |
| W-UX-01 | English-only interface, no Bengali font, mismatch with mockups | Low | Read |

---

## 2. Security

### W-SEC-01 — Customer data, staff PINs and the cipher key are web-served and in the Git repository
**Severity: Critical · Verified**

**Evidence.** `public/` is the web root. The folder `public/user_defined_assets/` contains, and `git ls-files` shows 46 of its files tracked and pushed to the GitHub remote:

| File | Content |
| :--- | :--- |
| `temp_erp_appScript/Contacts.html` | 1,769 real customers: names, phone numbers, children's ages, districts, call notes |
| `temp_erp_appScript/Activities.html`, `Inquiries.html` | 1,294 call logs and 2,260 inquiries |
| `temp_erp_appScript/Staff.html` | 13 staff members with their login PINs in plain text |
| `temp_erp_appScript/Sheet1.html` | The live web-app address of the interim system |
| `document/Pass encrypt and decrypt formula.pdf` | The cipher and its secret key |
| `temp_erp_appScript/temp_erp_appScript.zip` | A second copy of the above |

**Impact.** On any deployed copy of this application, anyone can download these files by URL without signing in. Anyone with access to the repository already has them. With the web-app address and a PIN, a person can sign in to the interim system as any staff member, including Admin.

**Fix, in this order.**
1. Change every PIN in the interim Staff sheet today.
2. Check whether the GitHub repository is public; make it private if so.
3. Move `user_defined_assets` out of `public/` (for example to `docs/` or outside the repository). Keep only the logo images the application actually displays, under `public/img/`.
4. Remove the data exports, the zip, the PDF and `prompt.txt` from Git history (`git filter-repo`), then force-push. Deleting them in a new commit is not enough.
5. Keep customer exports out of the repository permanently; add the folder to `.gitignore`.
6. Treat the phone numbers as exposed if the repository was ever public.

The documents in this folder should move with the rest.

### W-SEC-02 — Sales executives can view and export all leads
**Severity: Critical · Verified**

**Evidence.** `app/Http/Controllers/LeadController.php:570` allows five roles onto the leads page, and neither `index` (line 30) nor `export` (line 290) limits the query by owner or team. In the probe, executive A opened `/leads` and saw executive B's student name and phone number, and the CSV export contained it. `tests/Feature/AllLeadsTest.php:32` asserts that a Sales Executive may open the page.

**Impact.** This breaks the first rule of every specification version: owner-based visibility. Any executive can take the whole customer list or call another executive's customers — the "lead poaching" the module exists to prevent.

**Fix.** Scope every lead query by role: executive → own; team leader → own team; supervisor → assigned teams; admin → all. Remove list and export from the executive role altogether (FR-WORK-12); they get My Tasks. Put the rule in a `LeadPolicy` and a query scope, and change the test to assert a 403.

### W-SEC-03 — Self-service role change lets a sales executive become Admin
**Severity: Critical · Verified**

**Evidence.** `app/Http/Controllers/EmployeeProfileController.php:72` accepts `role` (and `status`) from any employee editing their own profile and stores it in a change request. `app/Actions/ReviewEmployeeChangeRequest.php:47` checks only that the approver outranks the employee's *current* role, then applies the request at line 78. In the probe, a Sales Executive requested `role = Admin`, a Team Leader approved, and the executive became Admin.

**Impact.** Full takeover of the system through two ordinary accounts, or one careless approval.

**Fix.** Remove `role` and `status` from the fields an employee may request; only an admin changes them. In the review action, also require that the approver outranks every role named in the request. Add a test for both.

### W-SEC-04 — Team leaders and supervisors can alter or delete any team
**Severity: High · Verified**

**Evidence.** `app/Http/Controllers/TeamController.php:708–715` admits Admin, Supervisor and Team Leader to every action, with no check of which team. Validation accepts any employee ID as leader, supervisor or member. In the probe, the leader of one team renamed another team, made a Sales Executive its leader, moved its real leader into the member list, then deleted it.

**Impact.** Any team leader can dismantle the organisation structure or move executives between teams, which also moves who can see their leads.

**Fix.** Create, edit, deactivate, delete and membership changes: Admin only (FR-TEAM-01). Supervisors and team leaders view their own teams. Validate roles: leader must be a Team Leader, supervisors must be Supervisors, members must be active Sales Executives.

### W-SEC-05 — Suspended and inactive employees keep their access
**Severity: High · Verified**

**Evidence.** Employment status is checked only during sign-in (`AuthenticatedSessionController.php:41`). The middleware that runs on every page (`EnsureEmployeeOnboardingComplete.php`) does not check it. In the probe, an executive suspended while signed in still opened the dashboard. A "remember me" cookie would also sign them back in later.

**Impact.** A dismissed employee keeps working access to customer data until their session expires — two hours of inactivity by default, or indefinitely with "remember me".

**Fix.** Check `status === 'Active'` in the middleware and sign out otherwise. When status changes, delete that user's rows in the `sessions` table and reset `remember_token`.

### W-SEC-06 — Weak password rules and no password recovery
**Severity: High**

**Evidence.** Passwords must be 6 to 12 characters (`PasswordController.php:24`, `EmployeeController`, `DatabaseSeeder`). A maximum length of 12 limits strength for no benefit. There is no "forgot password" flow although the login mockup shows one, and no screen for an admin to reset an employee's password. The only way to recover an account is to edit the database.

**Fix.** Minimum 8, a generous maximum (the stored value is twice the password's length in bytes and the column holds 255 characters, so up to about 100 characters is safe), reject very common passwords. Add an admin "reset to temporary password" action that sets the forced-change flag (FR-AUTH-08).

### W-SEC-07 — The requested reversible password scheme is breakable
**Severity: High · Design analysis**

`prompt.txt` asks for the PDF algorithm so that an admin can read any staff password. It is not implemented yet. **On 16 November 2026 it was confirmed as the method to use (Q-01).** Its weaknesses are recorded here so they are known properties of the design, followed by the measures that reduce them without changing the formula.

| Weakness | Explanation |
| :--- | :--- |
| The key is public | It is printed in the PDF, which is web-served and in the repository (W-SEC-01). Anyone with a database copy and the PDF reads every password |
| The key is fixed and in the design | It cannot be changed without re-encrypting every password, and it lives with the code rather than in protected configuration |
| One known password reveals the key | The steps before the key is added use no secret. Anyone who knows their own password and sees its stored value subtracts one from the other and obtains the key bytes — every employee can do this for their own account |
| No randomness | The same password always produces the same stored value, so shared or reused passwords are visible at a glance |
| No integrity check | A stored value can be altered and will still decrypt to something |
| It is not an encryption standard | The PDF says so itself in section 8 and recommends established authenticated encryption |
| Raw bytes | The output is not text and needs an encoding the PDF does not define |

Beyond the algorithm, **any** recoverable password store means one leak exposes every employee's actual password — which many people reuse on personal accounts — and means an admin can act as an employee with no trace of who really did it.

**Measures to apply with the decided method** — none changes the formula; the full procedure is in document 04 §16.

| # | Measure | Addresses |
| :-- | :--- | :--- |
| 1 | Use a new key. The one printed in the PDF is in the repository history and the web-served folder | Public key |
| 2 | Keep the key in the server's environment configuration only — not in code, documents or the repository | Fixed key in the design |
| 3 | Never display, log or export a stored value, so no employee can pair their own password with it | Key recovery from a known password |
| 4 | Restrict database and backup access to the same few people who may hold the key | Leak of database plus key |
| 5 | Decrypt screen for Admin only: the admin re-enters their own password, gives a reason, and the use is written to the audit log | Untraceable use |
| 6 | Tell staff that administrators can read their passwords, so nobody reuses a personal one | Harm beyond the ERP |
| 7 | Give every existing account a new temporary password at switch-over | One-way hashes cannot be converted |

**What remains after these measures:** a copy of the database together with the key — or together with any one known password — reveals every password; equal passwords have equal stored values; and anything an admin does while signed in with a staff password is recorded as that staff member's action, with only the decrypt-screen log pointing back to the admin.

The alternatives considered and not chosen — one-way hashing with an audited "open as employee" function, or the framework's standard authenticated encryption — are described in document 09, Q-01, should the policy be revisited.

### W-SEC-08 — The last admin can remove their own admin role
**Severity: Medium · Verified**

**Evidence.** `EmployeeProfileController.php:142` applies an admin's edits to their own record directly. In the probe the only admin set their role to Sales Executive; zero admins remained.

**Fix.** Refuse any change that would leave no active admin (FR-EMP-10).

### W-SEC-09 — No central authorisation; role checks are scattered
**Severity: Medium**

**Evidence.** There are no policy classes. Thirteen controllers each have their own private role check (`adminFor`, `employeeFor`, `authorizeAdmin`, `authenticatedEmployee`, `authorizeAssignment`, `authorizeLeadImport`) with its own role list. The lists already disagree: quick-create allows Team Leader and Supervisor to add leads, the upload page does not; the navigation shows links by yet another set of conditions.

**Impact.** W-SEC-02 and W-SEC-04 are direct results. Each new screen adds another place to get it wrong.

**Fix.** One policy per model, registered once; one permission matrix (document 04 §5); controllers call `authorize`.

### W-SEC-10 — No audit trail for sensitive actions
**Severity: Medium**

Role changes, status changes, settings changes, lead assignment, exports and duty overrides leave no record beyond an `edited_by` stamp that the next edit overwrites. Add the audit log of document 05 §3.5.

### W-SEC-11 — Unrestricted lead export, open to formula injection
**Severity: Medium**

**Evidence.** `LeadController::export` streams all matching leads to anyone allowed on the page, without a log entry. Cell values are written as-is, so a student name beginning with `=`, `+`, `-` or `@` — which arrives from public forms — runs as a formula when the file is opened in a spreadsheet.

**Fix.** Limit export to management roles and their reach, log each export, and prefix risky cell values with an apostrophe.

### W-SEC-12 — Development settings unsafe for production
**Severity: Low**

`.env` has `APP_DEBUG=true` (error pages reveal code and configuration), `APP_NAME=Laravel`, no secure-cookie setting, and the database user `root`. Correct values must be part of a deployment checklist.

---

## 3. Functional defects

### W-BUG-01 — Quick-create crashes when the phone already exists
**Severity: High · Verified**

**Evidence.** `LeadController.php:227` creates a duplicate-review record using column names that do not exist on the table (`source_id`, `student_name`, `raw_payload`, `match_criteria`, …). The real columns `incoming_lead_data` and `match_reason` are never set. In the probe the request returned HTTP 500: "NOT NULL constraint failed: duplicate_lead_reviews.incoming_lead_data". The transaction rolls back, so nothing is saved and the user sees an error page. The other single-entry form (`LeadUploadController::storeSingle`) does it correctly.

**Fix.** Replace both forms with one service (FR-ING-03). Add a test for the duplicate path.

### W-BUG-02 — Phone numbers are not canonical; duplicates pass
**Severity: High · Verified**

**Evidence.** `User::normalizePhoneNumber` (`app/Models/User.php:29`) removes spaces and punctuation and turns a leading `00` into `+`. It does not convert `+880` to `0` or restore a missing leading zero, and the `PhoneNumber` rule accepts any 7–15 digits. In the probe, `01711000002`, `+8801711000002` and `1711000002` were stored as three separate leads with no duplicate warning.

**Impact.** Meta lead exports use `+880…`; spreadsheets drop leading zeros. Both are everyday inputs. The same customer will be given to different executives. The interim system suffered exactly this and fixed it (document 01 §4.5).

**Fix.** The normaliser of document 04 §6, in its own class, used for leads and logins. Normalise existing data once.

### W-BUG-03 — Duplicate detection is narrow and implemented three times
**Severity: High**

**Evidence.** `LeadController.php:224`, `LeadUploadController.php:157` and `ImportLeadsFromCsv.php:55` each compare only the incoming primary number with stored primary numbers. Alternative and WhatsApp numbers are ignored, so the cross-number case that v-01 §6 singles out is missed. There is no automatic suppression of exact repeats: every match becomes a manual review. A lead approved from review is created without any check and is not tied to the existing owner.

**Impact.** Missed duplicates on one side; on the other, an admin review for every repeat inquiry — 22% of submissions in the interim data.

**Fix.** One duplicate service implementing document 04 §7, backed by the `lead_phones` table.

### W-BUG-04 — Manual assignment overwrites ownership without a trace
**Severity: Medium · Verified**

**Evidence.** `LeadController::assign` (line 135) accepts any lead and any active employee. In the probe an admin reassigned a lead that was already owned and in follow-up to an Accounts user: the request succeeded, `previous_owner_id` stayed empty, no batch, no history, and the previous owner's follow-up remained pointing at them.

**Fix.** Assignment only through the assignment service: target must be an active Sales Executive; reassignment of an owned lead is a separate action with a reason that moves follow-ups and writes history.

### W-BUG-05 — Imported leads do not take the campaign's course
**Severity: Medium**

**Evidence.** `ImportLeadsFromCsv.php:77` and `LeadUploadController::storeSingle` create leads with the source but never set `interested_course_id`, although every campaign has a course and the upload mockup promises "Default course" on every row. In the interim data only 3% of leads have a course for the same reason.

**Fix.** Set the course from the campaign unless the row names one.

### W-BUG-06 — Bulk import is all-or-nothing, CSV only, 500 rows
**Severity: Medium**

**Evidence.** `ImportLeadsFromCsv.php:205` rejects the whole file if any row fails. `MAX_ROWS = 500` (line 18). `LeadUploadController.php:94` accepts `csv,txt` only. The mockup shows Excel upload, a valid/review/invalid summary, import of the valid rows and a downloadable error file.

**Impact.** One wrong phone in 400 rows means the marketer must find it and upload again. The historical load in the interim system was 1,500 rows.

**Fix.** Two-step import with a result summary and error download; Excel support (needs a library — Q-17); background processing for large files.

### W-BUG-07 — Dashboard queue figures are hidden by the date range
**Severity: Medium**

**Evidence.** In `DashboardController.php:75–90`, overdue follow-ups, pending payments, pending transfers and pending duplicate reviews are all limited to the selected range, which defaults to today. A follow-up that fell overdue yesterday disappears from the admin's count.

**Fix.** Queue and backlog figures are always totals. The date range applies only to flow figures such as leads created and conversions.

### W-BUG-08 — Availability is computed three different ways
**Severity: Medium**

**Evidence.** `Employee::effectiveAvailabilityStatus` (line 193) returns "Available" whenever a shift exists; the employee directory repeats the logic in SQL (`EmployeeController.php`); the teams pages count only executives who have a roster override row for today (`TeamController.php:112`, `:246`), so they show zero available unless someone created overrides. None of them looks at the clock, the cut-off, working days or batch state. An admin can also type "Lazy", "Busy" or "Resigned" into a single date's override (`EmployeeController.php:173`).

**Fix.** The single computed availability of document 04 §2.

### W-BUG-09 — Application time zone is UTC
**Severity: Medium**

`config/app.php:68`. "Today", shift windows, the cut-off and overdue checks are six hours off for Dhaka. A lead created at 02:00 local time counts as yesterday. Set `Asia/Dhaka` before any time-based rule is built.

### W-BUG-10 — The enrollment action disagrees with the specification
**Severity: Medium**

`app/Actions/CreateEnrollmentFromLead.php`:

| Line | Behaviour | Specification |
| :-- | :--- | :--- |
| 98 | Sets the lead to Converted at once | "Enrolled – Pending Payment" until Accounts verifies (v-03 §9.1) |
| 89 | Accepts any employee as the sales executive | Only the lead's owner converts |
| 41 | Accepts the final amount as typed, unrelated to fee and discount | Final = fee − discount |
| — | Stores the installment count but creates no installment rows | A schedule with amounts and dates |
| — | Does not check that the course is active | Inactive courses do not enrol |

It is unreachable today, so no data is affected. Fix when the conversion screen is built.

### W-BUG-11 — Batch capacity can be set below reserved seats
**Severity: Low**

`CourseBatchController.php:158` validates capacity as at least 1. Lowering it below the admitted count leaves the batch overbooked while the vacancy figure shows 0. Refuse a capacity below the current reservation.

### W-BUG-12 — Lead screens create campaigns that break campaign rules
**Severity: Low**

Quick-create and both upload paths call `LeadSource::firstOrCreate` with a channel and a free-text campaign name. This creates campaign rows with no course and no chosen mode, by roles that are not allowed to create campaigns, bypassing `CampaignController`'s validation. Lead entry should only select existing campaigns.

### W-BUG-13 — Removing or deactivating people and teams leaves leads stranded
**Severity: Medium**

Setting an employee Inactive, removing a member from a team, or deactivating a team changes nothing on their leads: ownership, team and follow-ups stay as they were, attached to someone who can no longer act. Deleting a team that has transfer records will fail with a database error, because only leads are checked first (`TeamController.php:687`). Implement document 04 §12.

---

## 4. Architecture

### W-ARCH-01 — Core engine absent; tables without logic
**Severity: High**

Eight tables (`lead_batches`, `lead_batch_items`, `lead_feedbacks`, `lead_followups`, `lead_transfers`, `installment_schedules`, `payments`, and `enrollments` beyond one action) have models, factories and relationships but no code that writes them. Dashboards and team pages already read them and so display zeros. The risk is one of perception: screens and the v-03 checklist suggest a working sales system that is not there.

### W-ARCH-02 — Large controllers, repeated code, no services or policies
**Severity: Medium**

`TeamController` is 721 lines and `LeadController` 597. The same three dropdown queries appear four times in `TeamController`; lead creation is written three times; the phone-field loop four times; the filter validation twice. v-03 §14 prescribes a service layer under `app/Modules/Lead`; the code uses `app/Actions` with three classes. `AGENTS.md` forbids new base folders without approval, so the two instructions conflict (Q-17). Recommended: keep `app/Actions` and add `app/Services`, `app/Policies`, `app/Enums`, `app/Http/Requests` — standard Laravel folders.

### W-ARCH-03 — Statuses and roles are loose strings
**Severity: Medium**

Lead statuses, roles, call statuses and channels are typed as text in controllers, views, validation and tests. The definitions have already drifted: "open leads" excludes three statuses in `TeamController` but the leads filter groups "Not interested" with "Unreachable"; `MarketingExecutive` has no space while the other roles do; the roles enum is widened by a raw MySQL statement that other drivers skip. Introduce PHP enums and cast model attributes to them.

### W-ARCH-04 — No lead history is recorded
**Severity: High**

Creating, assigning, reassigning and merging a lead leave no record. `lead_feedbacks` will cover calls only. Every specification version requires a full, immutable timeline, and ownership disputes are the stated reason for the module. Add `lead_activities` (document 05 §3.3) before any feature that changes a lead, so no event is ever missed.

### W-ARCH-05 — Shifts have no management, names or working days
**Severity: Medium**

A shift is created as a side effect of typing times into a team or override form (`Shift::firstOrCreate`). It has no name and cannot be edited or retired. There is no weekly off day, although the interim system has one and v-03 mentions it. Add a shift screen and working days.

---

## 5. Performance

### W-PERF-01 — Query counts grow with teams and members
**Severity: Medium**

`TeamController::index` runs four count queries per team in a loop, then two more per active team for the chart. `TeamController::show` runs four queries per member. With 10 teams of 10, the index runs well over 70 queries and a team page over 60. `LeadController::index` runs five whole-table counts on every load. Replace with grouped aggregate queries.

### W-PERF-02 — Missing indexes and unindexed searches
**Severity: Low**

The composite indexes listed in v-03 were not created. Lead search uses `LIKE '%text%'` on five columns, which cannot use an index. Fine at 2,000 leads, slow at 100,000. Add the indexes of document 05 §2.7 and search phones by prefix or exact match through `lead_phones`.

### W-PERF-03 — Office ID generation can collide
**Severity: Low**

`Employee::nextOfficeId` (line 60) reads every office ID and picks the next number without a lock. Two admins creating employees at the same moment get the same ID and one fails on the unique index. Three ID formats also exist: `PED-nnn` (automatic), `ADMIN-001` (seeder), `EMP-nnnn` (factory).

---

## 6. Quality and operations

### W-QA-01 — Tests pass while defects exist; one test asserts a defect
**Severity: Medium · Verified**

154 tests pass. None covers the eight verified defects. `AllLeadsTest.php:32` requires that a Sales Executive can open the all-leads page; `Models/EmployeeTest.php:20` requires one-way password hashes while `prompt.txt` requires the opposite. A passing suite here means "nothing changed", not "the rules hold". Add a failing test for each defect before fixing it.

### W-QA-02 — Tests run on SQLite, production on MySQL
**Severity: Medium**

Enum handling, strict mode, the raw role migration, composite keys, raw SQL fragments in the employee directory and any future trigger behave differently or are skipped. Run the suite against MySQL in addition.

### W-OPS-01 — No scheduler, queue worker, backup or deployment notes
**Severity: Medium**

`routes/console.php` defines no task. Queue, cache and session all use the database with no worker described. There is no backup routine and no deployment document. The database is named `ERP_Pedago_Acagemy` (misspelt) where the specs say `pedago_erp_db`.

### W-OPS-02 — Documentation is outdated or misleading
**Severity: Medium**

`README.md` is the Laravel default. `db.md` says campaign and import routes do not exist (they do), that triggers are enforced (none exist), and omits columns that exist. v-03 ticks nine acceptance criteria that are not built. Details in document 07.

### W-UX-01 — English-only interface, no Bengali font, mismatch with mockups
**Severity: Low**

Staff use a Bangla interface today; the ERP is English only (Q-18). The layout loads Poppins and no Bengali font, so Bangla names render in a fallback font. The dashboard mockup specifies IBM Plex Sans. Several mockup elements are absent (document 01 §6).

---

## 7. Risks in the specified design

Not defects in the code — consequences of rules as written, to accept or change knowingly.

| # | Risk | Where | Suggestion |
| :-- | :--- | :--- | :--- |
| R-1 | A converted lead holds its batch slot until Accounts verifies payment. One lead awaiting payment stops that executive receiving any new batch, since a batch must be complete first | v-03 §9.1 | **Accepted on 16 November 2026 (Q-03).** Reduce the effect with a target verification time and an alert on the Accounts queue; exclude such leads from the Lazy rule (Q-04a); consider automatic verification of gateway payments (Q-15) |
| R-2 | Transfers auto-approve after 7 days with no approval from the receiving side | v-03 §11 | Keep, with a visible countdown; or auto-approve only the sender side |
| R-3 | Every repeat inquiry needs a manual review | v-03 §5.2 | **Resolved on 16 November 2026 (Q-02):** automatic rules with an inquiry log |
| R-4 | A roster alone makes absent staff eligible | v-03 §4 | Daily check-in (Q-06) |
| R-5 | An unpaid enrollment reserves a seat indefinitely | v-03 §9.3 | Alert, then cancel after a set number of days (Q-15) |
| R-6 | The lowest-numbered batch with space may have started months ago | v-03 §9.3 | Skip batches past a cut-off date, or let the executive choose (Q-15) |
| R-7 | Oldest-first distribution serves 1,200 historical leads before any fresh inquiry | Assumption | Campaign priority (Q-07) |
| R-8 | Lazy measured in clock hours penalises weekly days off | v-03 §3 | **Resolved on 16 November 2026 (Q-04):** both conditions are measured in working shifts and working days |
| R-11 | The batch-slot decision and the Lazy decision together would mark an executive Lazy while a customer has not yet paid | Q-03 with Q-04 | Count only leads not yet acted on towards the unfinished-batch condition (Q-04a, awaiting confirmation) |
| R-9 | A gateway-confirmed payment still waits for Accounts | v-03 §9 | Auto-verify gateway payments, with Accounts reconciling later (Q-15) |
| R-10 | Staff logins and customer phones are personal data with no stated retention or access policy | — | Define before go-live |


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

Reduced since the first update: stuck engaged leads and orphaned leads (Data health), lost history on hand moves (lead events), the same customer entered twice from another number (extra numbers, number check, merge), leads closed in error by wrong numbers (Invalid/Junk), the missing conversion path, silent overdue follow-ups (notifications), uncontrolled leave.

Still open or new:

- W-SEC-01 remains the first thing to fix: customer data and PINs under `public/user_defined_assets`, pushed to the repository.
- The scheduler must be running or nothing is distributed or notified; the Central Queue warns when it is not.
- Payment slips are only checked by a person, there is no gateway or bank feed.
- Labour Act figures are settings and have not been checked by a lawyer; the Act varies by establishment type.
- There is no gender field, so maternity leave is checked by service length only.
- The old Duplicate review queue and the automatic repeat rules both act on a duplicate; keep an eye on admins repeating work.
- No browser testing has been done on any screen.
