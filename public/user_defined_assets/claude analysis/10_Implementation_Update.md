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
2. Enrollment form with installment plan — without it the finance screens stay empty.
3. Phone normalisation and the lead history table.
4. Automatic duplicate rules and the inquiry log (decision Q-02).
5. Second Lazy condition (decision Q-04) with the rule for leads awaiting payment (Q-04a).
6. Notifications, receipts, payment gateways, data migration from the sheet.
