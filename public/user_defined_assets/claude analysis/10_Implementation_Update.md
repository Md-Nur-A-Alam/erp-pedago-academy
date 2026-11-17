# 10 — Implementation Update

What has been built since the analysis (documents 01–09 describe commit `85ef9a8`), how each part behaves, and what is still open. Written on 17 November 2026.

---

## 1. Summary

| Area | State |
| :--- | :--- |
| Admin panel (mockup scope) | Built |
| Panels for Team Leader, Sales Executive, Supervisor, Marketer | Built |
| Accounts | Payments review and installments built; no dashboard |
| Distribution engine with lead priority | Built |
| Password read and reset module | Built, admin only |
| In-app documentation and role guides | Built |
| Enrollment form, customer payment page, gateways, receipts | Not built |
| Notifications, audit log, data migration from the sheet | Not built |
| Securing `public/user_defined_assets` (W-SEC-01) | Not done — owner action |

Automated tests: 227, all passing. Routes: 82. Nothing in this build changes the existing `leads`, `employees` or `teams` data; two columns and one table were added (document 05).

---

## 2. Lead priority and the distribution engine

### 2.1 Temperature

| Temperature | Age since the lead was received | Setting |
| :--- | :--- | :--- |
| Hot | ≤ 60 minutes | `hot_lead_minutes` |
| Warm | ≤ 24 hours | `warm_lead_hours` |
| Cool | ≤ 72 hours | `cold_lead_hours` |
| Cold | older | — |

Temperature is computed from `leads.created_at` each time it is shown. It is not stored.

### 2.2 One cycle

1. **Who receives.** Active sales executives whose computed status is Available: on shift, outside the cut-off window, no open batch, not Lazy. They are ordered longest idle first (never had a batch, then oldest completed batch).
2. **How many.** `base = min(n, floor(L / S))`; if `base < n`, the first `L mod S` executives get one more. `n` is `lead_batch_size`, `L` the auto-queue size, `S` the available executives.
3. **Which leads.** Fresh leads (hot, warm) newest first; one slot in four reserved for the oldest stale lead; ranked by temperature, then high-intent channel, then newest.
4. **Dealing.** One lead per executive per round, hottest first.
5. **Result.** One Active batch per executive; each lead becomes Assigned with owner, team, assigner and time; a previous owner is remembered.

Only leads of campaigns in **Auto** mode are taken. Leads of **Manual** campaigns stay until an admin assigns them from the central queue.

### 2.3 Triggers

| Trigger | Condition |
| :--- | :--- |
| `leads:distribute` every minute (scheduler) | Engine switch ON |
| "Run distribution now" / "Run this cycle now" | Admin presses it; works with the switch OFF |
| Manual assignment in the central queue | Admin selects leads and an executive |

### 2.4 Call order for sales people

The executive's **My tasks** page is one ranked list: hot new leads; due call-backs (interested customers first, then longest overdue); warm, cool, cold new leads; call-backs later today. The team leader's and supervisor's dashboard lists assigned leads with no first call, longest wait first, and sorts the roster Lazy first, then by overdue follow-ups, then by uncalled leads.

---

## 3. Screens by role

| Role | Screens |
| :--- | :--- |
| Admin | Dashboard, Reports, Central queue, All leads, Upload leads, Campaigns, Duplicate review, Follow-ups, Transfers, Payments review, Installments, Teams & roster, Employees, Approvals, Courses, Subjects, Course batches, Documentation, Settings, Staff passwords |
| Supervisor | Dashboard (their teams), All leads (their teams), Follow-ups (their teams), Teams & roster, Approvals, My guide |
| Team Leader | Dashboard (their team), All leads (their team), Follow-ups (their team), Transfers (involving their team), Teams & roster, Approvals, My guide |
| Sales Executive | Dashboard, My tasks, My leads, Lead page with call feedback and transfer request, My follow-ups, My guide |
| Marketer | Dashboard, All leads, Upload leads, Campaigns, My guide |
| Accounts | Dashboard (shortcuts), Payments review, Installments, My guide |

The sidebar is a drawer on every screen size, opened from the menu button.

---

## 4. Rules as implemented

| Rule | Behaviour |
| :--- | :--- |
| Call feedback | Owner only. Call result, interest, note, next action. Follow-up needs a future time. Sets the lead's status, closes earlier open follow-ups, schedules the new one, frees the batch slot. |
| Lazy | A follow-up overdue more than `lazy_threshold_hours`. A Lazy executive is not Available, so receives no batch. |
| Reassignment | Admin moves an open follow-up and its lead to another executive, or returns the lead to the queue as New. |
| Transfer | Requested by the owner to an executive who is in a team. Leaders of both teams approve; a team without a leader needs none; auto-approved after `transfer_auto_approve_days`; admin can decide at once; rejection needs a reason. |
| Payment verification | Admin or Accounts. Verify: installment Paid, enrollment Enrolled, lead Converted, batch slot freed. Reject: reason required; installment, enrollment and lead return to awaiting payment. |
| Installments | Due soon = unpaid and due within `installment_reminder_days`; Overdue = unpaid and past due; cancelled enrollments are excluded. |
| Lead visibility | Sales executive: own. Team leader: own team. Supervisor: supervised teams. Marketer and Admin: all. Applies to list, export and lead page. |
| Team changes | Admin: any. Supervisor and team leader: only their teams. Create, delete, activate/deactivate: admin only. |
| Accounts | A non-active employee is signed out on the next request. The last active admin cannot be demoted or deactivated. Role and status cannot be self-requested. |

---

## 5. Password module

- Storage: `pdg1$` + lowercase hex of the documented procedure (reverse, chain, add repeating key). Verified test vector: password `abc`, key `KEY` → `ae0b1e`.
- Key: environment value `STAFF_PASSWORD_KEY`. Empty key → one-way hashing and the read function is switched off.
- Older bcrypt passwords still work and are converted at the owner's next sign-in. Until then the screen shows "After next sign-in".
- **Staff passwords** screen (admin only): *Show* and *Reset*. Both require the admin's own password and a written reason, are limited to ten attempts a minute, and write a row to `password_reveal_logs`. The password is displayed once, in the response to that request, and is not kept in the session.
- Remaining exposure: a copy of the database together with the key reveals every password. Keep the key out of Git and out of database backups.

---

## 6. In-app documentation

**Documentation** (admin) shows the permission matrix, ten rule chapters with the live setting values, the settings table, and each role's guide. **My guide** (every other role) shows only that role's guide: what they can and cannot do, a tour of their screens with links, the rules that apply to them, and working habits. A non-admin cannot open another role's guide or the rule book.

---

## 7. Repairs to existing pages

| Page | Repair |
| :--- | :--- |
| Leads | Quick-create with an existing phone no longer returns a server error; list and export scoped by role; manual assignment only to active sales executives and records the previous owner |
| Teams | Page crash from a template conflict fixed; permission checks per team; "available now" counts executives, not the leader |
| Employees | Self-requested role or status ignored; last admin protected; suspended and inactive accounts signed out |
| Dashboard | Queue, payment, transfer and follow-up figures are live; the date range applies to lead totals and the pipeline only |
| Employees, teams, courses, campaigns, leads, subjects, batches, duplicate review, approvals | 477 class replacements so that colours, radii, flat surfaces and the number font match the design system |

Not repaired: phone-number normalisation, the course on imported leads, Excel import, partial import with an error file.

---

## 8. Operating notes

| Need | How |
| :--- | :--- |
| Automatic distribution and transfer auto-approval | Run the scheduler: `php artisan schedule:work`, or cron `* * * * * php artisan schedule:run` |
| An executive to receive leads | Role Sales Executive, status Active, a shift (own, team or date override), inside shift hours |
| Readable passwords | `STAFF_PASSWORD_KEY` set before accounts are created |
| Front-end changes to appear | `npm run build` |

---

## 9. Still open, in suggested order

1. W-SEC-01: rotate the interim PINs, move `user_defined_assets` out of `public/`, purge the data files from Git history, confirm the repository is private.
2. (Done in section 15.) Enrollment form with installment plan.
3. (Done in section 16.) Phone normalisation and the lead history table.
4. (Done in section 16.) Automatic duplicate rules and the inquiry log (decision Q-02).
5. Second Lazy condition (decision Q-04) with the rule for leads awaiting payment (Q-04a).
6. Receipts, payment gateways, data migration from the sheet. (Notifications were done in section 14.)

---

## 10. Developer panel and icon rail (added 17 November 2026)

| Item | Behaviour |
| :--- | :--- |
| Sidebar | Collapses to an icon rail instead of disappearing. Labels stay available to screen readers and as tooltips; count badges become a dot. Desktop remembers the choice; on phones the expanded sidebar overlays the page |
| Developer role | New role with no authority level. Its sidebar has only the developer panel and the developer guide |
| View as | Signs the developer in as any active, non-developer employee; a banner offers direct switching and the way back. First-sign-in and profile steps are skipped; everything else is real |
| Tools | Run one distribution cycle, approve overdue transfers, clear compiled views |
| Log | `developer_activity_logs` records every switch and tool run |
| Switch | `DEVELOPER_PANEL`, on by default outside production |
| Guide | In the ERP under **Developer guide**: setup, access, switching, architecture, rule locations, settings, scheduler, interface kit, recipes, testing, troubleshooting |

---

## 11. Team leaders as sales people, KPIs, leave and full developer access (added 17 November 2026)

| Change | Behaviour |
| :--- | :--- |
| Team leader sells | Receives batches, has My tasks, the lead page with feedback, My follow-ups and transfer requests. Dashboard has two parts: "My work" and "My team". Counts as a sales person in the roster, the reports and the Lazy rule |
| Performance (all roles) | Four KPIs against company targets set in Settings: calls per working day (Fridays excluded), median first-call time, follow-ups kept on time (within 60 minutes), conversion rate; plus revenue collected. Own KPIs with a six-week trend for sales people; a table of people for leaders, supervisors and admins; marketing and Accounts figures for those roles |
| Leave (all roles but developers) | Apply with type, dates and reason; at most 30 days; no past start, no overlap. Decided by the team leader or supervisor (executives), the supervisor (team leaders) or an administrator (everyone else); nobody decides their own. Paid leave counts against a yearly allowance. Approved leave makes the status **On Leave**, so no new leads |
| Dashboards | Sales executive: status, calls today, ranked "Call next", KPIs, leave line, Lazy alert. Team leader: both parts. Supervisor: team KPIs, leave today and requests to decide, comparison, uncalled leads, roster |
| Developer | Holds every administrator permission (`Employee::isAdmin()` is true for the Developer role) and ranks above administrators for change requests. Only a developer can give the Developer role. Panel adds tools (cache, scheduler, engine on/off), recent errors and a shortcut grid |
| New data | `leave_requests`; settings `leave_casual_days`, `leave_sick_days`, `leave_earned_work_days`, `leave_maternity_days` (replacing `leave_days_per_year`), `kpi_calls_per_day`, `kpi_first_call_minutes`, `kpi_followup_on_time_percent`, `kpi_conversion_percent` |
| Definitions | A follow-up is kept on time when done at most 60 minutes after the promised time. First-call time is measured from assignment to the first logged call by anyone. Conversion counts leads assigned in the period that have since converted |

---

## 12. Developer database page (added 17 November 2026)

| Part | Behaviour |
| :--- | :--- |
| Source | Read live from the connected database (tables, columns, indexes, foreign keys, row counts), so it is never out of date |
| ER diagram | Tables grouped by module and coloured; foreign keys drawn as links with N and 1 ends; drag tables, pan, zoom, fit; click a table to highlight what it connects to; search tables and columns; show only keys or all columns; toggle modules |
| Tables | Sortable list with module, model, row count, columns, links out and in |
| Mapping | Table to Eloquent model to declared relations to the controllers and actions that use it |
| Relationships | Every foreign key with cardinality and on-delete and on-update rules |
| Detail panel | Purpose, columns with keys and defaults, links out and in, indexes, model, code that uses it |
| Export | SVG of the visible diagram, Mermaid erDiagram text, full schema as JSON |
| Access | Developers only; no row data is shown, only counts |


---

## 13. Leave under the Labour Act and two-step approval (added 17 November 2026)

| Part | Behaviour |
| :--- | :--- |
| Types | Casual (10 days a year, s.115), Sick (14, s.116; doctor's certificate confirmation above 3 days), Annual or earned (1 day per 18 days worked, s.117, usable after 1 year, held up to 40 days), Maternity (112 days, s.46, after 6 months of service, calendar days), Unpaid (no limit). All figures are settings |
| Counting | Friday is the weekly holiday and is not counted, except in maternity leave. A request that exceeds the balance is refused with advice to ask for the rest as unpaid leave. Festival holidays are not leave |
| Approval chain | Sales executive: team leader, then supervisor or admin (two approvals; one when the team has no leader). Team leader, marketer, accounts: one approval from a supervisor or admin. Supervisor: one approval from an admin. Admin and developer: none, approved on submission |
| Rules | A rejection at any step ends the request. An administrator can settle any request at any step. Nobody decides their own. The second approver sees a request only after the first approval |
| Data | `leave_requests` gains `required_approvals`, `approvals_done`, `has_certificate`, `first_reviewed_by_employee_id`, `first_review_note`, `first_reviewed_at`; type `Maternity` added |
| Scheduler warning | A heartbeat is written every minute by the scheduler. The Central Queue shows a warning when auto-distributable leads wait and no heartbeat was seen in 3 minutes |

Check the figures against your own employment terms or with legal counsel before relying on them; the Act has conditions (for example by type of establishment) that the system does not model.

---

## 14. Follow-up stages and notifications (added 17 November 2026)

| Part | Behaviour |
| :--- | :--- |
| Coming up | An open follow-up from `followup_notice_hours` (default 30) before its time until the exact time. The owner gets one reminder when the window opens (not for follow-ups set in the last 5 minutes) |
| Overdue | From the exact time until the owner logs a new follow-up, a conversion or another outcome (feedback closes the open follow-up). Announced at the exact time and repeated every `followup_overdue_repeat_hours` (default 6) |
| Lazy | Overdue longer than the Lazy threshold: the owner and the team leader, supervisors and administrators are told once a day |
| Dashboards | Sales executive and team leader: a My follow-ups card listing overdue and coming-up calls with a Call button. Team leader, supervisor and admin dashboards: overdue and coming-up counts. Follow-ups page tabs: Overdue, Coming up, Scheduled later, Completed |
| Notification centre | Laravel database notifications (`notifications` table), the bell with an unread count refreshed each minute, the Notifications page, open-and-mark-read, mark all read |
| Who is told | Executives and leaders: new batch, follow-ups, transfer and payment outcomes, conversions, leave decisions. Leaders and supervisors: transfers, Lazy people, leave to approve. Accounts: payments to verify. Marketing: campaign conversions. Admins and developers: duplicates, profile changes, payments, Lazy people, leave |
| Running | `notifications:followups` runs every minute from the scheduler. The scheduler must be running |
| Open point | The request said 30 hours; it is a setting, so change it if 30 minutes was meant |

---

## 15. Conversion flow and the interim Apps Script comparison (added 17 November 2026)

The interim Google Sheets script (`temp_erp_appScript/Code.txt`) was compared with the application. The conversion flow was the largest gap and is now built; the rest are listed for decision.

| Part | Behaviour |
| :--- | :--- |
| Enroll | The lead owner opens Enroll this student: course, discount (largest set in Settings, default 20%), 1 to 6 installments 30 days apart, first due date, student and guardian details. Final amount is computed on the server. A batch seat is reserved. Lead becomes Enrolled - Pending Payment, open follow-ups close |
| Payment | The owner (or an administrator) submits an installment: amount must equal the installment, method (bKash, Manual MFS, Manual Bank), transaction ID or slip (JPG, PNG, PDF up to 4 MB, kept on the private disk and shown only to Accounts, administrators, the owner and managers). Lead and enrollment become Payment Under Review, Accounts are notified |
| Verify | Accounts verify or reject (existing). Verified: installment Paid, enrollment Enrolled, lead Converted, batch slot released. Rejected: back to Pending, reason sent to the owner |
| Not yet built | Priority High/Medium/Low on feedback and My tasks tie-break; Invalid/Junk status tied to Wrong Number; Connected call needs a next follow-up; number check for sales people; alternate numbers that match duplicates; repeat inquiry log with reactivation to the previous owner; bulk paste import by campaign and course name with skip reasons; merge duplicate contacts; data health checks; unassigned reason on the queue; assignment notes in the lead history |

---

## 16. Interim script requirements, second version (added 17 November 2026)

`Code-2.txt` adds a guardian name and standing remark per lead, a per-rep Auto or Manual mode with a daily limit, and a WhatsApp thank-you. Everything missing from both scripts is now built.

| Area | Behaviour |
| :--- | :--- |
| New fields | `leads.priority` (High, Medium, Low), `leads.remarks`, status `Invalid/Junk`; `employees.assign_mode`, `daily_lead_cap`, `auto_count_date`, `auto_count_today` |
| New tables | `lead_phones` (extra numbers, unique), `lead_inquiries` (first inquiry and every repeat with its outcome), `lead_events` (assigned, returned, transferred, phone added or removed, WhatsApp, inquiry, merged) |
| Feedback rules | Wrong Number forces Invalid/Junk. Connected with Follow-up or Interested needs a call-back time. The form can set priority, correct the student name and guardian, and keep a standing remark. Priority breaks ties inside Call next |
| Numbers | Normalised to 01XXXXXXXXX (handles +880, 880, a lost zero) or +digits. Any of main, WhatsApp, other or extra numbers finds the lead. Number check page for sales, leaders, supervisors, marketing and admins shows owner, status and campaign only |
| Repeat inquiry | Closed lead: reopened, back to the previous owner outside the daily limit, or to the queue. Open lead: follow-up now when it is a new day. Enrolled: logged. The duplicate review queue stays for admins to approve a separate student such as a sibling |
| Assignment | Manual people are never auto-assigned. The daily limit counts engine assignments only and resets each day. Page: Assignment controls |
| WhatsApp | Setting `whatsapp_thanks_message` with {name}, {guardian}, {sales} (and the Bangla forms). The button opens WhatsApp and logs it; the system cannot see whether it was sent |
| Tools | Paste leads (name, phone, campaign, course, district, age, guardian, remarks), Merge duplicates, Data health (orphaned leads, no follow-up, old queue, odd numbers, big batches), queue shows why a lead is waiting |
| Not carried over | Daily reset by Apps Script triggers (the date is compared instead), the Sheets formatting repairs (not needed in a database), PIN login (replaced by hashed staff passwords) |
