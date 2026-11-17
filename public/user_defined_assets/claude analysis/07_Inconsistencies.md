# 07 — Inconsistency Register

Every place where two sources disagree: specification versions with each other, the specifications with `db.md`, the documents with the code, the code with itself, and the interim system and mockups with the specifications. Each has a proposed resolution; where the choice is yours, the question number from document 09 is given.

42 items.

---

<!-- build-update -->

## Build update — 17 November 2026

The inconsistencies below were recorded against commit `85ef9a8` and have not been re-marked one by one. In general: the gaps between the admin mockup and the application (missing sidebar items, engine switch, queue counts, next-cycle panel, roster, "needs your attention") are closed; the settings named in v-03 now exist under the application's key names; the v-03 acceptance checklist is still inaccurate and still needs resetting. One new inconsistency was created and is deliberate: decision Q-04 says Lazy has two conditions, and the code implements one (document 04, "Differences").

<!-- build-update -->


## A. Between specification versions

| ID | Topic | One source says | Another says | Proposed resolution |
| :-- | :--- | :--- | :--- | :--- |
| INC-01 | Meaning of "Lazy" | v-01: busy for 2 consecutive days or more. v-02: batch incomplete or busy for 2 working days | v-03: a follow-up overdue beyond threshold t | **Decided 16 Nov 2026:** either condition makes an executive Lazy — a follow-up overdue past the next working shift, or a batch unfinished for two working days (document 04 §9) |
| INC-02 | Transfer approval | v-01, v-02: approval of the sender's team leader or supervisor | v-03: both team leaders; auto-approval after 7 days. `db.md` adds an admin override that v-03's text does not mention | v-03 for cross-team; one approval for same-team; supervisor or admin may stand in; admin override kept — **Q-08** |
| INC-03 | When a converted lead frees its batch slot | v-01, v-02: when the action is recorded, like any outcome | v-03 §9.1: only when Accounts verifies the payment | **Decided 16 Nov 2026:** v-03 applies — the slot is held until Accounts verifies the payment |
| INC-04 | Roles | v-01: four roles, with "Team Leader / Sales Manager" | v-02, v-03: five, adding Accounts. `db.md` and code: six, adding Marketing Executive. Interim: Admin, Manager, Marketing Executive, Sales Executive | Six roles as in the code; add Marketing Executive to the specification's role table; map "Manager" — **Q-09** |
| INC-05 | Payment gateways | v-02: bKash, SSLCommerz, AamarPay | v-03: bKash, SSLCommerz | Follow v-03 — **Q-15** |
| INC-06 | Search by sales executives | v-01, v-02: may search a number that is in their own leads | v-03: no typed search; a button on an owned lead. Interim: may check any number and see owner and status only | v-03's in-context button; decide on the number check — **Q-12** |
| INC-07 | Scope dropped without notice | v-01 has first-action overdue, reopen rules, eight KPIs, a notification matrix, eleven search filters, leave handling, ownership record list | v-03 has none of them and does not say they were removed | Keep them, at priority 2 — **Q-14** |
| INC-08 | Scarcity rule and batch size | v-03 §3: n is configurable | v-03 §6.2: the thresholds are written as the numbers 2 and 3 | Use the general rule in document 04 §3.2, which gives v-03's results when n = 3 |
| INC-09 | Lazy threshold unit | v-03 §3: "Integer/Hours" | Same line: "Default: 24 hours or 2 consecutive working days" — two different defaults | **Decided 16 Nov 2026:** measured in working shifts and working days, as two settings (document 04 §15) |
| INC-10 | Status words used but never defined | v-03 text uses "Fully Completed", "Converted (Installment)", "Payment Approved & Verified", "Converted Follow-up", "Pending Approval" | None is in v-03's own status lists | Not statuses: derive from enrollment and installment state (document 04 §1.1, §11.2) |

## B. Specification versus `db.md`

| ID | Topic | Specification | `db.md` | Proposed resolution |
| :-- | :--- | :--- | :--- | :--- |
| INC-11 | Shift model | v-03 §4.1, §13: one `user_shifts` row per user holding shift times, on-duty flag and availability status | `shifts` master, `employee_rosters` per date, `employees.shift_id`; the code adds `teams.shift_id` | Keep the `db.md` structure; move availability off roster rows (document 05 §2.2, §2.4) |
| INC-12 | Lead columns | v-03 SQL: `lead_code`, `customer_name`, `primary_phone`, `alternate_phone`, `student_age`, `academic_background`, `lead_source` and `campaign_name` as text, `assigned_at`, `last_contacted_at` | `student_name`, `contact_number`, `alternative_contact_number`, `age`, `class`, `source_id` as a foreign key; no code, no assignment timestamps | Keep the names in the database; drop `lead_code`; correct v-03 §13 |
| INC-13 | Foreign key naming | v-03: `user_id`, `from_user_id`, `verified_by_user_id` | `employee_id`, `from_employee_id`, `verified_by_employee_id` | `employee_id` is correct, because business records point to employees |
| INC-14 | Password storage | `prompt.txt`: reversible, with an admin decrypt module, using the PDF | `db.md`: "one-way password hash". The PDF's section 8 advises against its own use for sensitive data | **Decided 16 Nov 2026:** reversible, by the PDF formula, with a new key held outside the repository (document 04 §16). `db.md` and the hashing test are to be changed |
| INC-15 | Supervisor to team | Both documents call it "1:N" — one supervisor, many teams | The table is a junction with a unique pair; the team form accepts several supervisors per team. That is many-to-many | Decide whether a team may have more than one supervisor — **Q-09** |
| INC-16 | `db.md` against itself | ER diagram: 7 channels; `leads_source` has 6 columns | Data dictionary: 11 channels; 10 columns including mode, active and creator | The dictionary is right; redraw the diagram |
| INC-17 | Where logic lives | v-03 §14: "will strictly follow" `app/Modules/Lead` | `db.md` §4.1 says logic "is encapsulated inside" those services, in the present tense | Neither exists; decide the structure — **Q-17** |
| INC-18 | Team leader cardinality | `db.md` diagram draws employee-to-teams as one-to-many, labelled "1:1 optional" | Code: a unique index makes it one team per leader | One team per leader; fix the diagram |

## C. Documents versus code

| ID | Topic | Document | Code | Proposed resolution |
| :-- | :--- | :--- | :--- | :--- |
| INC-19 | Acceptance checklist | v-03 §15 ticks nine of ten criteria | None of the nine is implemented (document 02 §6.1) | Reset the checklist; tick only what a test proves |
| INC-20 | Campaign and import routes | `db.md` §2.1.3: "no campaign-creation or lead-import routes yet" | Both exist and are tested | Update `db.md` |
| INC-21 | Database triggers | `db.md` §4: immutability triggers "enforced" | No trigger exists | Implement two, drop the third (document 05 §5) |
| INC-22 | Settings | v-03 §3: six settings, named `default_batch_size_n`, `shift_cutoff_minutes`, … | Two settings, named `lead_batch_size`, `assignment_cutoff_minutes`; neither is read by any logic | Keep the code's names, add the rest (document 04 §15), update v-03 |
| INC-23 | Columns missing from `db.md` | Not listed | `leads`: `location`, `student_parent_type`, `student_background`, `ad_set_id`, `previous_owner_id`, `assigned_by_employee_id`, `assigned_at`, `last_action_at`. `employees.team_id`. `teams.shift_id`. `lead_batches.assigned_by_employee_id`. The `shifts` table has no dictionary entry. `employee_rosters.shift_id` is now nullable | Regenerate the dictionary from the schema |
| INC-24 | Keys and indexes | v-03 §13: unique (batch, lead); indexes on phones and on (status, owner) | Not created | Add (document 05 §2.7, §2.8) |
| INC-25 | Lead status at conversion | v-03 §9.1: "Enrolled – Pending Payment" until verified | `CreateEnrollmentFromLead` and `docs/course-catalog-and-batches.md`: "Converted" at once | Follow v-03 |
| INC-26 | Duplicate review | v-03 §5.2: Admin or Supervisor; four actions; exact duplicates suppressed automatically | Admin only; three actions; every match is queued | Implement document 04 §7 |
| INC-27 | Lead upload | v-03 §5.1: Excel or CSV with columns Name, Phone, Alt Phone, Email, City, Course, Campaign, Source. Mockup: `.xlsx`, partial import, error file | CSV only; twelve columns, without course; all-or-nothing | Build to the mockup; channel and campaign chosen on screen, course as an optional column |
| INC-28 | Campaign needs one course | v-03 §9.3: every campaign is linked to exactly one course | Lead screens create campaign rows with no course through `firstOrCreate` | Lead entry selects existing campaigns only |
| INC-29 | Technical architecture | v-02 §14: Breeze, Sanctum, API controllers, Blade components. v-03 §12: an `/api/v1/…` endpoint and `LeadPolicy` | No Breeze, Sanctum, API, components or policies. `AGENTS.md` forbids new base folders and dependencies without approval | Server-rendered Blade without a separate API; policies yes — **Q-17** |
| INC-30 | Names and identity | v-03 header: database `pedago_erp_db` | `.env`: `ERP_Pedago_Acagemy`; `APP_NAME=Laravel`; `README.md` is the framework default | Rename at the next clean install; write a real README |

## D. Code versus code

| ID | Topic | In one place | In another | Proposed resolution |
| :-- | :--- | :--- | :--- | :--- |
| INC-31 | Single-lead entry | `POST /leads/create` → `LeadController::store`: assigns directly, crashes on a duplicate | `POST /leads` → `LeadUploadController::storeSingle`: no assignment, handles a duplicate | One form, one service |
| INC-32 | Who may add a lead | Quick-create: Admin, Marketing, Team Leader, Supervisor | Upload page single entry: Admin and Marketing only | The matrix in document 04 §5 |
| INC-33 | Availability | `Employee::effectiveAvailabilityStatus`: available when a shift exists | Employee directory: the same idea rewritten in SQL. Team pages: only roster override rows count | One computed function (document 04 §2) |
| INC-34 | "Open" and "closed" leads | `TeamController`: open = not Converted, Lost or Not Interested, so Unreachable counts as open | Leads filter: groups Not Interested with Unreachable. Interim: closed = Converted, Not Interested, Unreachable, Lost, Invalid/Junk | One definition in the status enum (document 04 §1.1) |
| INC-35 | Naming | Roles `Sales Executive`, `Team Leader` with spaces | `MarketingExecutive` without. Office IDs in three formats: `PED-001`, `ADMIN-001`, `EMP-1234`. Tables `leads_source`, `course_batch` singular among plural names; channel key `wp` for WhatsApp | Leave stored values; fix display labels through enums; one office ID format |
| INC-36 | Who can hold which position | Assign dropdown offers Sales Executives, Team Leaders and Supervisors as lead owners; leader dropdown offers Team Leaders, Supervisors and Admins | Validation accepts any active employee for owner, leader, supervisor and member | Validate roles — **Q-10** |

## E. Interim system and mockups versus specification

| ID | Topic | Interim system or mockup | Specification and ERP | Proposed resolution |
| :-- | :--- | :--- | :--- | :--- |
| INC-37 | Person and inquiry | One contact per phone, many inquiries; repeats are logged automatically; a closed lead reopens to its previous owner | One lead per inquiry; every phone match goes to manual review; no reopening on a new inquiry | **Decided 16 Nov 2026:** inquiry log and automatic rules (document 04 §7) |
| INC-38 | Batch behaviour | Topped up to 3 *unactioned* leads; a new lead goes at once to the least-loaded executive with room | Strict batch, completed before the next; longest-idle order; scarcity rule; no top-up | Follow v-03 — **Q-07** |
| INC-39 | Availability | Signed in + one office schedule + a weekly off day | Per-person roster and shift; signing in is irrelevant; no weekly off day in the schema | Roster plus a daily check-in and working days — **Q-06** |
| INC-40 | Status and call lists | Statuses include Unassigned and Invalid/Junk; call results include No Answer and Switched Off; follow-up has a date only; leads have a priority | No Invalid; different call list including Under Age and Over Age; follow-up has date and time; no priority | Document 04 §1.1 and §4.2 — **Q-13** |
| INC-41 | What executives may do | Add their own referral lead, self-assigned; check a number | v-03: only Admin and Team Leader enter leads; no number search | **Q-12** |
| INC-42 | Mockups versus the build | Dashboard and upload use IBM Plex Sans with a Bengali font; 7 channels; navigation for queue, follow-ups, transfers, payments, installments, reports; "Forgot password"; engine switch; global search | The app loads Poppins only; 11 channels; those modules do not exist | Load a Bengali font; treat the mockup navigation as the target map |

---

## Summary by kind

| Kind | Count | Decided 16 Nov 2026 | Still needs your decision |
| :--- | --: | --: | --: |
| Between specification versions | 10 | 3 | 5 |
| Specification versus `db.md` | 8 | 1 | 2 |
| Documents versus code | 12 | 0 | 1 |
| Code versus code | 6 | 0 | 1 |
| Interim system and mockups versus specification | 6 | 1 | 4 |
| **Total** | **42** | **5** | **13** |

The other 24 have a clear correct side and can be fixed without a decision.

## Documents to update once decisions are made

| Document | What to change |
| :--- | :--- |
| Spec v-03 | Role table (six roles), settings names, status list, §13 SQL to match the database, §15 checklist reset, the two-condition Lazy rule, the automatic repeat-inquiry rules, the dropped v-01 sections |
| `db.md` | Diagram and dictionary regenerated from the schema; triggers section; the outdated remarks in §2.1.3; `users.password` described as the reversible stored value, not a one-way hash |
| `docs/course-catalog-and-batches.md` | Lead status at conversion |
| `README.md` | Project description, setup, scheduler and queue instructions |
| `prompt.txt` | Remove from the repository; its password instruction is now recorded in document 04 §16 |


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

Found while building:

- The legacy action `CreateEnrollmentFromLead` converts the lead immediately. The specification, and decision v-03, convert only when Accounts verify a payment. The new `EnrollStudent` flow passes the status `Enrolled - Pending Payment`; the legacy default is kept only because tests depend on it.
- The request said follow-up reminders start 30 hours before. Thirty minutes may have been meant. It is a setting.
- The interim script's `Converted` could be set by any rep in the feedback form. The application removes that option.
- The interim script reactivates leads in Converted too. The application does not reopen converted or enrolled leads.
- Two sources of availability: the script used a manual online flag, the application derives it from shifts, roster and leave.
