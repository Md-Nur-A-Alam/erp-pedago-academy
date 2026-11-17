# 05 — Target Database Design

What must change in the schema to support documents 03 and 04, table by table, followed by the mapping for migrating the interim Google Sheet.

The existing 25 tables are kept. Nothing here requires dropping data. Every change is an additive migration unless marked otherwise.

---

<!-- build-update -->

## Build update — 17 November 2026

Schema changes actually made so far:

| Change | Purpose |
| :--- | :--- |
| `lead_transfers.decided_by_employee_id`, `lead_transfers.decision_note` | Who decided a transfer, and why |
| New table `password_reveal_logs` (admin, target, action Revealed/Reset, reason, IP, time) | Access log of the password module |
| `employee_rosters.availability_status` gains `Cut Off` and `Holiday` | Availability states |
| `users.password` now holds `pdg1$<hex>` (or an older bcrypt hash until next sign-in) | Reversible storage, decision D-1 |
| Seven new `system_settings` keys | Engine switch, priority windows, Lazy, transfer and reminder days |

Not yet made: `lead_phones`, `lead_inquiries`, `lead_activities`, `distribution_runs`, `audit_logs`, `notifications`, `import_runs`, `receipt_counters`, and the database guards of §4. Lead temperature is computed from `created_at` and needs no column.

<!-- build-update -->


## 1. Design principles

1. **Store decisions, compute states.** Availability, vacancy, overdue and "fully paid" are derived from facts, not typed.
2. **The database guards the rules that must never break:** one owner, one active batch, one open batch item, one pending transfer, matching course and batch, unique transaction IDs.
3. **History is append-only.** Feedback, timeline, transfers and payments are never updated in ways that lose information.
4. **One definition of each list.** Statuses, roles and channels come from PHP enums; database enums or check constraints mirror them.
5. **Phones are canonical and indexed** in one lookup table.
6. **Conventions stay consistent** with what exists: `edited_at` / `edited_by` on master data, standard timestamps on transactional data.

---

## 2. Changes to existing tables

### 2.1 `users`

| Change | Reason |
| :--- | :--- |
| `password` keeps its type (varchar 255) but now holds the hexadecimal result of the reversible procedure in document 04 §16, not a one-way hash | Decided, Q-01 |
| Every existing row gets a new temporary password and `must_change_password = true` at switch-over | A one-way hash cannot be converted |
| Remove the 5 rows that have no employee, after checking them | Orphan login accounts |
| Raise the minimum password length in validation; the 12-character maximum is no longer needed, since the procedure works at any length (not a schema change) | W-SEC-06 |

The key is not stored in the database. It lives in the server's environment configuration.

### 2.2 `employees`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `status` — add value `Resigned` | enum | v-03 treats Resigned as permanent; today it exists only as a typed roster word |
| `checked_in_at` | timestamp null | Presence for eligibility (Q-06) |
| `paused_at`, `paused_by`, `pause_reason` | timestamp null, FK null, varchar null | "Unavailable" set by management |
| `lazy_since`, `lazy_grace_until` | timestamp null | Lazy state and manual clearing |
| `idle_since` | timestamp null, indexed | Longest-idle ordering |
| `deactivated_at`, `deactivated_by` | timestamp null, FK null | Audit of leaving |
| Index (`role`, `status`, `team_id`) | | Eligibility query |

### 2.3 `shifts`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `name` | varchar(100), unique | Shifts are unnamed today and created by typing times |
| `working_days` | set or JSON of weekdays | Weekly off day — the interim system has it, the ERP does not |
| Unique (`shift_start_time`, `shift_end_time`, `working_days`) | | Prevents duplicates from `firstOrCreate` |

### 2.4 `employee_rosters`

| Change | Reason |
| :--- | :--- |
| Keep as per-date overrides: `is_on_duty`, `shift_id` | This part works |
| Add `reason` varchar and `created_by` FK | Leave, training, swap — and who entered it |
| Stop writing `availability_status` and `last_active_at`; drop them once the engine is live | Availability is computed (document 04 §2). A per-date row cannot hold Lazy or Resigned |

### 2.5 `teams`, `supervisor_teams`

| Change | Reason |
| :--- | :--- |
| Unique index on `teams.name` | Enforced only in validation today |
| No other change | — |

### 2.6 `leads_source` (campaigns)

| Column | Type | Reason |
| :--- | :--- | :--- |
| `priority` | enum(High, Normal, Low), default Normal | Queue order; lets a historical backlog wait behind fresh leads |
| Unique (`channel`, `campaign_name`) | | Enforced only in validation today |
| Make `course_id` required for rows with a name, by a check or in the service | | Lead screens create course-less campaigns today |

Longer term the table mixes two ideas: a channel and a campaign. Rows with no name act as "the channel itself". That works, but must be the only way such rows are created — through one service, never `firstOrCreate` in a controller.

### 2.7 `leads`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `status` — add `Invalid`; retire `Contacted`, `Interested` after data is mapped | enum | Document 04 §1.1, Q-13 |
| `interest_level` | varchar(30) null | Latest customer interest |
| `priority` | enum(High, Medium, Low) null | In use in the interim system |
| `attempt_count` | unsigned smallint, default 0 | Rule for Unreachable |
| `first_contacted_at`, `first_contacted_by` | timestamp null, FK null | Ownership record required by v-01 §24 |
| `next_followup_at` | datetime null, indexed | Fast task lists; kept in step with `lead_followups` |
| `closed_at`, `closed_reason` | timestamp null, varchar null | Lost, Invalid and Not Interested reasons |
| `reopened_at`, `reopened_by`, `reopen_reason` | | v-01 §36 |
| `converted_at`, `converted_by` | timestamp null, FK null | Conversion owner |
| `created_by` | FK null | Missing today; v-01 §24 |
| `queued_reason` | varchar null | Why a lead waits in the queue |
| `legacy_id` | varchar(30) null, unique | The sheet's `LD-nnn`, for migration checks |
| Index (`status`, `current_owner_id`) | | In v-03, missing in the migration |
| Index (`current_owner_id`, `next_followup_at`) | | Task screens |
| Index (`team_id`, `status`) | | Team dashboards |
| Index (`status`, `created_at`) | | Queue order |

Notes:

- `lead_code` from v-03 is not added. The interface already shows `L-<id>`; a second identifier adds nothing.
- `contact_number`, `whatsapp_number` and `alternative_contact_number` stay as convenient display columns. `lead_phones` (§3.1) is the search index.
- `location` stays free text. The interim "District" maps into it.

### 2.8 `lead_batches`, `lead_batch_items`

| Change | Reason |
| :--- | :--- |
| `lead_batches.type` enum(Auto, Manual) | How it was issued |
| `lead_batches.engine_run_id` FK null | Traceability to the run |
| One active batch per employee: a generated column that is the employee ID while Active and null otherwise, with a unique index | NFR-09 |
| `lead_batch_items`: unique (`batch_id`, `lead_id`) | In v-03, missing in the migration |
| One open item per lead: the same generated-column technique on `lead_id` while incomplete | NFR-09 |
| `lead_batch_items.first_action_at`, `unworked_flagged_at` | Unworked rule |

### 2.9 `lead_feedbacks`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `followup_id` | FK null | Which follow-up this answered |
| `batch_item_id` | FK null | Which batch item it completed |
| `attempt_number` | unsigned smallint | Attempt history |
| `outcome_reason` | varchar null | Lost and Invalid reasons |
| `corrects_feedback_id` | FK null | Correction notes (v-01 §37) |
| Change `customer_interest` to nullable | | Not meaningful when the call did not connect |
| Change FK on `lead_id` from cascade to restrict | | Deleting a lead must not erase history |
| Immutability triggers from `db.md` §4.2 (MySQL), plus a model guard that refuses update and delete | | Documented as enforced, absent today |

### 2.10 `lead_followups`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `type` | enum(Sales, Installment, Transferred-in, Payment-rejected, Reinquiry) | One due list, labelled |
| `installment_schedule_id` | FK null | Installment tasks |
| `done_at`, `done_feedback_id` | timestamp null, FK null | How it was closed |
| `closed_reason` | enum(Done, Superseded, Cancelled) null | Distinguishes real completion |
| `due_notified_at` | timestamp null | Reminder sent once |
| Index (`employee_id`, `is_done`, `followup_date_time`) | | Task and Lazy queries |
| One open follow-up per lead: generated column + unique index | | Document 04 §4.3 |

### 2.11 `lead_transfers`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `status` — add `Cancelled` | enum | Withdrawals and system cancellations |
| `requested_by_employee_id` | FK | The requester is not always the owner (admin transfers) |
| `rejected_by_employee_id`, `rejection_reason`, `rejected_at` | | Rejection needs a reason |
| `cancel_reason` | varchar null | |
| `auto_approve_at` | timestamp | Countdown shown in the inbox |
| One pending transfer per lead: generated column + unique index | | NFR-09 |
| Trigger from `db.md` §4.3 is not needed | | `transferred_at` is set by the service in the same transaction |

### 2.12 `duplicate_lead_reviews`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `source_id` | FK | Held only inside the JSON today |
| `match_type` | enum(Cross number, Different student, Different course, Multiple) | Specific reasons |
| `other_matched_lead_ids` | JSON null | When several leads match |
| `inquiry_id` | FK null | Link to the inquiry log |
| `created_lead_id` | FK null | The lead produced by "approve" |

### 2.13 `enrollments`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `student_age`, `student_background` | | On the v-02 form, missing here |
| `discount_amount` | decimal(10,2) | Stored result of the percentage |
| `discount_approved_by`, `discount_approved_at` | FK null, timestamp null | Q-15 |
| `link_expires_at` | timestamp null | Customer page |
| `customer_confirmed_at` | timestamp null | The customer reviewed the details |
| `enrolled_at`, `fully_paid_at` | timestamp null | Milestones instead of extra statuses |
| `cancelled_at`, `cancelled_by`, `cancel_reason` | | Cancellation record |
| Check: `final_deal_amount = base_course_fee − discount_amount` | | Amounts are accepted independently today |

### 2.14 `installment_schedules`

| Change | Reason |
| :--- | :--- |
| Index (`status`, `due_date`) | Reminder and overdue jobs |
| No other change | — |

### 2.15 `payments`

| Column | Type | Reason |
| :--- | :--- | :--- |
| `payment_channel` | enum(bKash Personal, bKash Merchant, Nagad, Rocket, Bank, Gateway) | "Manual MFS" cannot tell Nagad from Rocket |
| `payer_account` | varchar(50) null | "Sent-from number" required by v-03 §10 |
| `gateway_reference`, `gateway_payload` | varchar null, JSON null | Gateway confirmation |
| `receipt_number` | varchar(20) null, unique | Money receipt |
| `submitted_by` | enum(Customer, Staff) | Who entered it |
| Unique (`payment_channel`, `transaction_id`) | | A transaction ID cannot be used twice |
| Check: `transaction_id` or `slip_screenshot_path` is present for manual payments | | BR-16 |
| Change FK on `installment_schedule_id` from set-null to restrict | | A payment must keep its installment |

### 2.16 `system_settings`

| Change | Reason |
| :--- | :--- |
| Add `type` enum(boolean, integer, string) and `updated_by` | Typed values; the interim system broke on a mistyped time |
| Seed the missing keys of document 04 §15 | Four of v-03's six are absent |

---

## 3. New tables

### 3.1 `lead_phones`

All numbers of all leads in canonical form.

| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | PK | |
| `lead_id` | FK, cascade | |
| `phone` | varchar(25), indexed | Canonical |
| `kind` | enum(Primary, WhatsApp, Alternative, Other) | |
| `added_by`, `created_at` | | |
| Unique (`lead_id`, `phone`) | | |

Replaces three separate column searches with one indexed lookup, and allows more than one alternative number, which the interim system already supports.

### 3.2 `lead_inquiries`

One row per submission (decided, Q-02).

| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | PK | |
| `lead_id` | FK null | The lead created or matched |
| `source_id` | FK | Channel and campaign |
| `course_id` | FK null | |
| `payload` | JSON | The data as received |
| `outcome` | enum(Created, Suppressed duplicate, Sent to review, Merged, Discarded, Reactivated) | |
| `created_by`, `created_at` | | |
| `legacy_id` | varchar null | The sheet's `INQ-nnn` |
| Index (`lead_id`), (`source_id`, `created_at`) | | Campaign reports count inquiries, not only leads |

### 3.3 `lead_activities`

The timeline. Append-only.

| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | PK | |
| `lead_id` | FK, restrict | |
| `actor_employee_id` | FK null | Null means the system |
| `type` | varchar(40) | created, assigned, requeued, feedback, followup_scheduled, followup_done, transfer_requested, transfer_approved, transfer_rejected, transfer_auto_approved, reassigned, status_changed, reopened, reinquiry, data_changed, enrollment_created, enrollment_cancelled, payment_submitted, payment_verified, payment_rejected, note |
| `from_value`, `to_value` | varchar null | For status and owner changes |
| `meta` | JSON null | Reason, related record IDs |
| `created_at` | timestamp | |
| Index (`lead_id`, `created_at`) | | |

Protected by the same triggers and model guard as `lead_feedbacks`.

### 3.4 `distribution_runs`

| Column | Notes |
| :--- | :--- |
| `id`, `trigger`, `queue_size`, `eligible_count`, `batch_size_setting`, `leads_assigned`, `plan` JSON, `duration_ms`, `status`, `error`, `created_at` | Engine log and the source of the dashboard's "next cycle" panel |

### 3.5 `audit_logs`

| Column | Notes |
| :--- | :--- |
| `id`, `actor_employee_id`, `action`, `subject_type`, `subject_id`, `before` JSON, `after` JSON, `ip_address`, `reason`, `created_at` | Role and status changes, settings, exports, password decryption, overrides, password resets |

### 3.6 `notifications`

Laravel's standard table (`php artisan make:notifications-table`). No custom design.

### 3.7 `import_runs`

| Column | Notes |
| :--- | :--- |
| `id`, `uploaded_by`, `source_id`, `file_name`, `total_rows`, `imported`, `duplicates_suppressed`, `sent_to_review`, `invalid`, `error_file_path`, `status`, `created_at`, `finished_at` | Upload history, background processing, downloadable error rows |

### 3.8 `receipt_counters`

| Column | Notes |
| :--- | :--- |
| `year` PK, `last_number` | Locked when issuing a receipt number, so numbers have no gaps |

---

## 4. Integrity guards that belong in the database

| Rule | Mechanism |
| :--- | :--- |
| One active batch per executive | Generated column + unique index |
| One incomplete batch item per lead | Generated column + unique index |
| One open follow-up per lead | Generated column + unique index |
| One pending transfer per lead | Generated column + unique index |
| Batch belongs to the enrollment's course | Composite foreign key — exists |
| Topic belongs to the course's subject | Composite foreign key — exists |
| One enrollment per lead | Unique — exists |
| Transaction ID used once per channel | Unique index |
| Manual payment has proof | Check constraint |
| Amount arithmetic on enrollment | Check constraint |
| Feedback and timeline are immutable | Triggers + model guard |
| History survives lead deletion | Restrict instead of cascade on history FKs |

A virtual generated column with a unique index works on MySQL 8 and on SQLite, so tests exercise the same guard. It should be confirmed with a small test migration before it is relied on. Triggers are MySQL only; the model guard covers tests, and the suite should also run on MySQL before release (NFR-21).

---

## 5. Decisions on the triggers described in `db.md`

| `db.md` trigger | Decision |
| :--- | :--- |
| Block DELETE on `lead_feedbacks` | Implement, and add the same for `lead_activities` |
| Block UPDATE on `lead_feedbacks` | Implement |
| Set `transferred_at` on approval | Do not implement — the service sets it in the same transaction, which is testable |

`db.md` should then describe exactly what exists.

---

## 6. Migrating the interim Google Sheet

### 6.1 Order

1. Staff → `users`, `employees`
2. Courses → `courses`
3. Campaigns → `leads_source`
4. Contacts → `leads`, `lead_phones`
5. Inquiries → `lead_inquiries`
6. Activities → `lead_feedbacks`, `lead_followups`, `lead_activities`
7. Verification report

### 6.2 Staff

| Sheet | ERP | Rule |
| :--- | :--- | :--- |
| StaffID | kept in a mapping file | Used to translate `AssignedTo` and `StaffID` |
| Name | `employees.name` | |
| PIN | **not migrated** | Every account gets a new temporary password and the forced-change flow. The PINs in the export are compromised (W-SEC-01) |
| Role | `employees.role` | SalesExecutive → Sales Executive; MarketingExecutive → MarketingExecutive; Admin → Admin |
| Team | — | Empty for everyone; teams are assigned in the ERP |
| Active | `employees.status` | No → Inactive |
| IsOnline | not migrated | Unreliable |
| — | `users.phone_number` | **Not in the sheet. Each person's login phone must be collected** |

### 6.3 Courses and campaigns

| Sheet | ERP | Rule |
| :--- | :--- | :--- |
| Four courses named "Spoken English" | One ERP course | Merge; map all four IDs to it |
| "General inquiry" course | No course | Leads keep a blank course |
| Campaign name, mode, active | `campaign_name`, `distribution_mode`, `is_active` | |
| Source "Facebook", "Facebook/Meta Ads" | channel `fb` | |
| Source "Manual Entry" | channel `manual` | |
| Blank source (the historical campaign) | channel `other`, priority Low | |
| — | `course_id` | Required for named campaigns; an admin chooses for each of the five |

### 6.4 Contacts → leads

| Sheet | ERP | Rule |
| :--- | :--- | :--- |
| ContactID | `leads.legacy_id` | |
| Name | `student_name` | 80% of ages are 3–13, so the name is the child's or the parent's — keep as given |
| Phone | `contact_number` + `lead_phones` Primary | Normalise again with the ERP function; report any that fail |
| SecondaryPhones | `lead_phones` Alternative; first one also in `alternative_contact_number` | |
| District | `location` | |
| Age | `age` | |
| CampaignID | `source_id` | Blank → the "unknown" channel row |
| CourseID | `interested_course_id` | Blank → the campaign's course |
| AssignedTo | `current_owner_id`, `team_id` | Inactive staff → no owner, requeue |
| Priority | `priority` | |
| CreatedAt, CreatedBy | `created_at`, `created_by` | |
| LastActivityAt | `last_action_at` | |
| NextFollowUpDate | one open `lead_followups` row, time set to the owner's shift start | The sheet has a date only |
| Notes | a `note` entry in `lead_activities` | |
| UnassignedReason | `queued_reason` | |

**Status mapping**

| Sheet status | ERP status | Extra |
| :--- | :--- | :--- |
| Unassigned | New | No owner |
| New | Assigned | Placed in a migrated batch so the executive starts with their current work |
| Contacted | Follow-up | If there is no follow-up date, create one due on go-live day |
| Interested | Follow-up | `interest_level` = Interested; follow-up as above |
| Follow-up | Follow-up | |
| Converted | Converted | No enrollment exists; marked "legacy conversion" in the timeline (Q-19) |
| Not Interested | Not Interested | |
| Unreachable | Unreachable | |
| Lost | Lost | |
| Invalid/Junk | Invalid | |

### 6.5 Inquiries and activities

| Sheet | ERP | Rule |
| :--- | :--- | :--- |
| Inquiry row | `lead_inquiries` | First inquiry of a contact → outcome Created; later ones → Suppressed duplicate |
| Activity with CallStatus "Note" | `lead_activities` type note | 319 rows |
| Activity with a real call status | `lead_feedbacks` + `lead_activities` type feedback | 975 rows |
| CallStatus | `call_status` | Kept as recorded |
| Notes | `sales_note` | Blank → "(no note recorded)" |
| StatusAfter | `next_action` | Mapped by the status table above |
| — | `customer_interest` | "Not recorded" — the sheet never captured it |
| Timestamp, StaffID | `created_at`, `employee_id` | History inserts bypass the immutability guard once, during migration only |

### 6.6 Verification report

The migration is rehearsed on a copy and must report:

- 1,769 contacts → leads created, by status, against the sheet's counts.
- 2,260 inquiries → inquiry rows.
- 1,294 activities → 975 feedback rows + 319 notes.
- Phones that failed normalisation, with row references.
- Numbers that appear on more than one contact after normalisation.
- Leads whose owner could not be mapped.
- Open leads without a follow-up after mapping — must be zero.

### 6.7 Cut-over

1. Announce a freeze time; set the sheet to read-only.
2. Export; run the migration; review the report.
3. Staff sign in with temporary passwords, change them, complete their profiles.
4. Admin sets teams, shifts and campaign courses.
5. Switch the engine on.
6. Keep the sheet read-only for reference; withdraw the old web app link.


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

Schema changes made since the first update:

| Change | Purpose |
| :--- | :--- |
| New table `notifications` (Laravel database notifications) | Inbox for every role |
| `lead_followups.reminder_sent_at`, `overdue_notified_at` | Reminder and repeat notice tracking |
| `leave_requests` (new) with `required_approvals`, `approvals_done`, `has_certificate`, `first_reviewed_by_employee_id`, `first_review_note`, `first_reviewed_at`; type adds Maternity | Leave and the two-step chain |
| `developer_activity_logs` (new) and role `Developer` | Developer panel audit |
| `leads.status` adds `Invalid/Junk` | Closed outcome |
| `leads.priority` (High, Medium, Low), `leads.remarks` | Priority and standing remark |
| `employees.assign_mode`, `daily_lead_cap`, `auto_count_date`, `auto_count_today` | Auto or Manual and the daily limit |
| `lead_phones` (lead_id, phone_number unique, label, added_by_employee_id) | Extra numbers of a lead |
| `lead_inquiries` (lead_id, source_id, interested_course_id, outcome, created_by_employee_id) | Every inquiry and repeat |
| `lead_events` (lead_id, employee_id, type, note, created_at) | Non-call history |
| `system_settings` keys | Follow-up window and repeat, leave days (casual, sick, certificate, earned, earned cap, maternity), discount cap, installments, WhatsApp message, KPI targets |
| Private disk `payment-slips/` | Payment slips |

Of the tables listed as not made above, `lead_phones`, `lead_inquiries`, `notifications` and the history table (as `lead_events`) now exist. Still not made: `distribution_runs`, `audit_logs`, `import_runs`, `receipt_counters`, and the database guards of section 4. The live structure, with an ER diagram, is on the Developer Database page.
