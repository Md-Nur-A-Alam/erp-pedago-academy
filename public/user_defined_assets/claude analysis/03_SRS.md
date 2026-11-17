# 03 — Software Requirements Specification

**Product:** Pedago Academy ERP — Sales Lead Distribution, Enrollment and Payment Module
**Version of this SRS:** 1.0 (consolidated from spec v-01, v-02, v-03, `db.md`, the interim system and the UI proposals)
**Date:** 16 November 2026

---

<!-- build-update -->

## Build update — 17 November 2026

The build status column in the requirement tables below is the baseline of commit `85ef9a8` and has **not** been re-marked row by row. Status by module is now:

| Module | Baseline | Now |
| :--- | :--- | :--- |
| M1 Authentication | Mostly built | Built, including reversible storage, admin read/reset with log, and sign-out on suspension. Self-service "forgot password" is still not built. |
| M2 Employees and permissions | Partial | Lead and team scoping by role built; self role/status requests blocked; last admin protected. Formal policy classes not written. |
| M3 Teams and availability | Partial | Availability computed from shift, cut-off, batch and Lazy. Check-in not built. |
| M6 Lead ingestion | Partial | Unchanged, except the quick-create crash is fixed. Excel, partial import and phone normalisation still open. |
| M8 Distribution engine | Not built | Built: queue, allocation rule, longest-idle order, batches, manual assignment, engine switch, scheduled run. Run log not built. |
| M9 Sales workspace | Not built | Built: ranked task list, lead page, call feedback, follow-up scheduling. Name correction, extra numbers and inquiry log not built. |
| M10 Overdue and Lazy | Not built | Overdue lists, Lazy flag from overdue follow-ups, reassignment and return to queue built. Lazy from an unfinished batch (decision Q-04) not built. |
| M11 Transfers | Schema only | Built: request, two-leader approval, rejection with reason, auto-approval, admin override. |
| M12–M13 Enrollment and portal | Schema only | Unchanged: no enrollment form, no customer page, no gateways. |
| M14 Accounts | Schema only | Payment verify/reject and installment views built. Receipts and reminders not built. |
| M15 Notifications | Not built | Not built. |
| M16 Dashboards and reports | Partial | Dashboards for Admin, Supervisor, Team Leader, Sales Executive and Marketer; reports page for Admin. No Accounts dashboard. |
| M17 Settings | Partial | Nine typed settings with ranges, including the engine switch. |
| M18 History and audit | Not built | Password access log only. Lead timeline is feedback and follow-ups; no general audit log. |
| M19 Data migration | Not built | Not built. |

New requirements added in this build:

| # | Requirement | Status |
| :-- | :--- | :-- |
| FR-DIST-13 | The engine shall rank queue leads by temperature (hot, warm, cool, cold), newest first, with high-intent channels ahead inside a temperature. | Built |
| FR-DIST-14 | One slot in every four of a cycle shall go to the longest-waiting stale lead. | Built |
| FR-WORK-13 | The executive's task list shall be one ranked list: hot new leads, due call-backs, then remaining leads by freshness. | Built |
| FR-RPT-09 | The team dashboard shall list assigned leads that have not had a first call, longest wait first, and sort the roster by need. | Built |
| FR-SET-06 | Hot, warm and cold windows shall be settings. | Built |
| FR-HELP-01 | Administrators shall see the full rule book inside the ERP. | Built |
| FR-HELP-02 | Every other role shall see only its own training guide. | Built |

<!-- build-update -->


## 1. Introduction

### 1.1 Purpose

This document is the single requirement reference for building the module to completion. It merges three specification versions into one numbered list, adds what the interim system proves is needed, and records the current build status of every requirement.

### 1.2 Scope

In scope: staff accounts and roles; teams, shifts and availability; course catalogue; campaigns; lead intake and duplicate handling; automatic batch distribution; the sales workspace; follow-ups and the Lazy rule; transfers and reassignment; conversion, enrollment and installments; the customer payment page; accounts verification and receipts; notifications; dashboards and reports; settings; history and audit; migration from the interim Google Sheet.

Out of scope for this module: class delivery, attendance, teacher management, payroll, general accounting ledgers, marketing-platform API integration (Meta lead forms), a mobile app, a public student portal beyond the payment page.

### 1.3 Definitions

| Term | Meaning |
| :--- | :--- |
| Lead | One sales opportunity: a student, a contact number and a course interest |
| Inquiry | One submission or contact event that produced or touched a lead |
| Central queue | Leads with status New and no owner, waiting for distribution |
| Batch | A set of up to n leads issued to one sales executive at one time |
| Batch item | One lead inside a batch; completed when its required outcome is recorded |
| n | The global batch size, default 3 |
| Cut-off | The number of minutes before shift end when new batches stop |
| Owner | The one employee currently responsible for a lead |
| Follow-up | A scheduled future contact on a lead, with date and time |
| Lazy | An availability state that blocks new batches because follow-ups are overdue |
| Enrollment | The admission record created when a lead converts |
| T−2 | Two days before an installment due date |
| SE, TL | Sales Executive, Team Leader |

### 1.4 Reading the requirement tables

**Priority** — P1: needed to replace the interim system. P2: needed for a controlled operation. P3: later.
**Status** — Built · Partial · Schema only · Not built (as of commit `85ef9a8`).
**Source** — the document that states the requirement. "Interim" means the Apps Script system. "New" means no source states it but the system cannot work without it. `Q-nn` points to an open question in document 09; `D-n` points to a decision already made, listed in §2.5.

---

## 2. Overall description

### 2.1 Users

| Role | Count today | Uses the system for |
| :--- | :-- | :--- |
| Admin | 2 | Configuration, people, full oversight, overrides |
| Supervisor | 0 | Oversight of assigned teams |
| Team Leader | 2 in ERP dev data; none in the interim system | Running one team: monitoring, approvals, reassignment |
| Sales Executive | 8 active in the interim system | Calling leads, recording feedback, follow-ups, enrollment |
| Marketing Executive | 1 | Campaigns, lead upload |
| Accounts | 0 | Verifying payments, issuing receipts |
| Customer (parent or student) | — | Reviewing an enrollment and paying, without an account |

### 2.2 Operating environment

Web application on Laravel 13 and PHP 8.5 with MySQL 8. Server-rendered Blade pages styled with Tailwind CSS v4. Used on office desktops and on phones. Time zone Asia/Dhaka. Volume today: under 2,000 leads and 13 staff; the design target is 100,000 leads and 100 staff without redesign.

### 2.3 Constraints

- Staff sign in with a phone number, not email.
- The project's own rules (`AGENTS.md`) forbid new top-level folders and new dependencies without approval; this affects the `app/Modules/Lead` structure in v-03 and any Excel, SMS or payment package (Q-17).
- Payment gateways need merchant accounts with bKash and SSLCommerz.
- Customer contact data is personal data of parents and children.

### 2.4 Assumptions

Each is a default taken where the sources are silent; each maps to a question in document 09.

| # | Assumption | Question |
| :-- | :--- | :-- |
| A-1 | The central queue is global; any eligible executive in any active team can receive any lead | Q-05 |
| A-2 | A lead's team is always its owner's team | Q-05 |
| A-3 | An executive must check in for the day to become eligible | Q-06 |
| A-4 | The queue is served by campaign priority, then oldest first | Q-07 |
| A-5 | Only Sales Executives own leads | Q-10 |
| A-6 | Same-team transfers need one approval; a supervisor or admin may act for a team leader | Q-08 |
| A-7 | Leads waiting for payment do not count towards the "unfinished batch" Lazy condition | Q-04a |

### 2.5 Decisions already made (16 November 2026)

| # | Decision | Question |
| :-- | :--- | :-- |
| D-1 | Staff passwords are stored with the reversible method of the password document; Admin has a decrypt screen | Q-01 |
| D-2 | Repeat inquiries are handled by automatic rules, with every submission logged as an inquiry | Q-02 |
| D-3 | A converted lead keeps its batch slot until Accounts verifies the first payment | Q-03 |
| D-4 | An executive is Lazy when a follow-up is overdue past the next working shift, or when the batch has been unfinished for two working days | Q-04 |

---

## 3. Functional requirements

### M1 — Authentication and onboarding

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-AUTH-01 | Staff sign in with a normalised phone number and password | P1 | db.md | Built |
| FR-AUTH-02 | Sign-in attempts are rate limited per phone and IP address | P1 | New | Built |
| FR-AUTH-03 | Only Active employees have access; access ends immediately when status changes, including open sessions and remembered logins | P1 | v-01 §28 | Partial — checked at sign-in only |
| FR-AUTH-04 | A new account must change its temporary password at first sign-in | P1 | db.md | Built |
| FR-AUTH-05 | A new account must complete six profile fields before using the system | P1 | db.md | Built |
| FR-AUTH-06 | A user can change their own password by giving the current one | P1 | New | Built |
| FR-AUTH-07 | Sign-out ends the session; sessions are regenerated at sign-in | P1 | New | Built |
| FR-AUTH-08 | An admin can reset an employee's password to a temporary one that must be changed | P1 | Login mockup | Not built |
| FR-AUTH-09 | Passwords are stored with the reversible method of the password document. An admin-only decrypt screen shows a staff member's password; every use is logged with admin, employee, time and reason | P1 | prompt.txt, D-1 | Not built — passwords are one-way hashed today |

### M2 — Employees, roles and permissions

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-EMP-01 | Admin creates an employee with login, role, designation, joining date and an automatic office ID | P1 | db.md | Built |
| FR-EMP-02 | Admin directory with search, role, status and availability filters, sortable | P1 | New | Built |
| FR-EMP-03 | Six roles with a fixed authority order: Admin > Supervisor > Team Leader > Sales Executive = Accounts = Marketing Executive | P1 | db.md | Built |
| FR-EMP-04 | Employees maintain their own personal details; national ID is encrypted | P1 | db.md | Built |
| FR-EMP-05 | Changes to name, office ID, designation, joining date and login phone need approval from a higher role. Role and employment status are changed by Admin only | P1 | db.md | Partial — role and status are requestable |
| FR-EMP-06 | An admin edit applies at once and closes the employee's pending requests | P1 | db.md | Built |
| FR-EMP-07 | Every permission in §5 of document 04 is enforced on the server through one central policy layer | P1 | v-01 §34 | Partial — scattered role checks |
| FR-EMP-08 | A supervisor's reach is limited to assigned teams; a team leader's to their own team — for every screen and action | P1 | all | Not built |
| FR-EMP-09 | Deactivating, suspending or marking an employee resigned ends their access and releases their leads under FR-OVR-07 | P1 | v-01 §28 | Not built |
| FR-EMP-10 | The system always keeps at least one active admin | P1 | New | Not built |

### M3 — Teams, shifts and availability

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-TEAM-01 | Admin creates, edits, deactivates and deletes teams; members must be sales executives, leaders team leaders, supervisors supervisors | P1 | v-03 §2 | Partial — not limited to Admin, no role validation |
| FR-TEAM-02 | A team has one leader; a leader leads one team | P1 | db.md | Built |
| FR-TEAM-03 | Admin assigns teams to supervisors | P1 | v-03 §2 | Built |
| FR-TEAM-04 | Team page: members, shift, status, batch progress, overdue count, pipeline, 30-day performance | P2 | v-01 §29 | Partial — availability figures unreliable, slow |
| FR-TEAM-05 | Admin maintains named shifts with start time, end time and working days | P1 | v-02 §6 | Partial — shifts are created implicitly, no days |
| FR-TEAM-06 | An employee's shift for a date is: date override, else team shift, else personal default | P1 | v-02 §6 | Built |
| FR-TEAM-07 | Admin or TL records a per-date duty override: off duty, or a different shift | P1 | v-02 §6 | Partial — also accepts engine states by hand |
| FR-TEAM-08 | Weekly off days and approved leave make an employee off duty | P1 | v-03 §4, Interim | Not built |
| FR-TEAM-09 | Availability is computed, not typed: Available, Busy, Unavailable, Off Duty, Lazy, Resigned, from the rules in document 04 §2 | P1 | v-03 §4.2 | Not built |
| FR-TEAM-10 | TL, supervisor or admin can pause an executive (Unavailable) and resume them | P1 | v-03 §4.2 | Not built |
| FR-TEAM-11 | An executive starts their working day with a check-in; the system ends it at shift end | P1 | Interim, Q-06 | Not built |

### M4 — Course catalogue

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-CAT-01 | Admin maintains subjects | P1 | v-03 §9.3 | Built |
| FR-CAT-02 | Admin maintains topics within a subject; names unique per subject | P1 | v-03 §9.3 | Built |
| FR-CAT-03 | Admin maintains courses: title, unique code, description, conditions, subject, topic, version, category, base fee, active flag | P1 | db.md | Built |
| FR-CAT-04 | A course's topic must belong to its subject, guaranteed by the database | P1 | v-03 §9.3 | Built |
| FR-CAT-05 | Admin maintains course batches: number, name, short code, capacity, start date; one "latest" per course | P1 | v-03 §9.3 | Built |
| FR-CAT-06 | Admitted count and vacancy are derived from enrollments; referenced records cannot be deleted | P1 | v-03 §9.3 | Built |

### M5 — Campaigns

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-CMP-01 | Admin and Marketing create campaigns: channel, name unique within the channel, one course, distribution mode | P1 | v-03 §9.3 | Built |
| FR-CMP-02 | Campaign list with filters, lead counts and statistics | P2 | v-01 §31 | Built |
| FR-CMP-03 | Campaigns can be deactivated; a campaign with leads cannot be deleted | P1 | New | Built |
| FR-CMP-04 | Leads of a Manual campaign wait for manual assignment; leads of an Auto campaign enter automatic distribution; an inactive campaign accepts no new leads | P1 | db.md, Interim | Partial — stored, never applied |
| FR-CMP-05 | Campaign and channel performance: leads, interested, converted, lost, conversion rate, by date range | P2 | v-01 §31 | Not built |

### M6 — Lead ingestion

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-ING-01 | Bulk upload of CSV and Excel files against a chosen channel and campaign, with a downloadable template and selectable columns | P1 | v-03 §5.1, mockup | Partial — CSV only |
| FR-ING-02 | The upload shows a result before saving: valid, to duplicate review, invalid. Valid rows are imported; invalid rows are downloadable with reasons | P1 | Mockup | Partial — one invalid row rejects the file |
| FR-ING-03 | One single-lead entry form, used by every role allowed to add leads, applying the same rules as bulk upload | P1 | v-03 §5.1 | Partial — two forms, different rules, one crashes |
| FR-ING-04 | Every phone number is stored in one canonical form on every entry path (document 04 §6) | P1 | Interim | Partial — symbols stripped only |
| FR-ING-05 | Template download containing the selected columns and an example row | P2 | Mockup | Built |
| FR-ING-06 | Only Admin and Marketing Executive upload in bulk | P1 | db.md | Built |
| FR-ING-07 | A lead takes its interested course from the campaign unless the row names another | P1 | Mockup | Not built |
| FR-ING-08 | Files larger than 500 rows are processed in the background with a progress and result record | P2 | Interim | Not built |
| FR-ING-09 | Every submission is recorded as an inquiry against the lead it created or matched | P1 | Interim, D-2 | Not built |
| FR-ING-10 | A new valid lead enters the central queue and triggers distribution | P1 | v-03 §6.1 | Not built |

### M7 — Duplicate detection and review

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-DUP-01 | Each incoming number is compared with every stored number of every lead: primary, alternative and WhatsApp | P1 | v-01 §6, v-03 §5.2 | Partial — primary to primary only |
| FR-DUP-02 | Same phone, same name, same course and same campaign is a definite duplicate: no new lead, the inquiry is logged | P1 | v-03 §5.2 | Not built |
| FR-DUP-03 | Any other phone match goes to the review queue with a specific reason: cross-number, different student, different course | P1 | v-03 §5.2 | Partial — one generic reason |
| FR-DUP-04 | Review queue with search and status filter, showing incoming and matched records side by side | P1 | v-03 §5.2 | Built |
| FR-DUP-05 | Decisions: approve as separate lead, merge, discard; each review is decided once | P1 | v-03 §5.2 | Built |
| FR-DUP-06 | Fourth decision: edit the incoming data, then dispatch | P2 | v-03 §5.2 | Not built |
| FR-DUP-07 | Supervisors review duplicates for their teams | P2 | v-03 §2 | Not built |
| FR-DUP-08 | Automatic rules for repeat inquiries on the same lead: a closed lead is reopened to its previous owner; an open lead is resurfaced to its owner | P1 | Interim, D-2 | Not built |

### M8 — Distribution engine

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-DIST-01 | Central queue screen: unassigned leads with age, campaign, mode and reason for waiting | P1 | v-01 §29, mockup | Not built |
| FR-DIST-02 | Master switch: when off, no automatic batch is issued; existing batches continue | P1 | v-03 §3 | Not built |
| FR-DIST-03 | Only eligible executives receive batches (document 04 §2.2) | P1 | v-03 §4 | Not built |
| FR-DIST-04 | A batch records executive, size, time and how it was issued | P1 | v-03 | Schema only |
| FR-DIST-05 | A batch is complete when all its items are complete; one active batch per executive | P1 | all | Schema only |
| FR-DIST-06 | Allocation follows the scarcity model generalised for any n (document 04 §3) | P1 | v-03 §6.2 | Not built |
| FR-DIST-07 | Leads are served oldest first; executives are served longest idle first | P1 | v-03 §6.2, Q-07 | Not built |
| FR-DIST-08 | The engine runs when leads enter the queue, when an executive becomes available, when the switch turns on, and on a timer | P1 | v-03 §6.1 | Not built |
| FR-DIST-09 | No batch is issued after an executive's cut-off; unfinished batches carry to the next working day | P1 | all | Not built |
| FR-DIST-10 | Two engine runs can never assign the same lead or give one executive two active batches | P1 | New | Not built |
| FR-DIST-11 | Admin and TL assign queue leads by hand through the same assignment service, with a batch and a history entry | P1 | v-01 §22 | Partial — a direct assign exists that bypasses all rules |
| FR-DIST-12 | Next-cycle preview and a log of each engine run: leads, executives, rule applied, result | P2 | Mockup | Not built |

### M9 — Sales workspace

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-WORK-01 | My Tasks with four sections: Current batch, Follow-up due, Overdue, Upcoming | P1 | v-01 §12 | Not built |
| FR-WORK-02 | Lead page for the owner: customer data, source, interest, status, last action, notes, next follow-up | P1 | v-01 §13 | Not built |
| FR-WORK-03 | Feedback form: call status, customer interest, concern, sales note, next action | P1 | v-03 §7.1 | Schema only |
| FR-WORK-04 | Next action is one of Follow-up, Converted, Not Interested, Unreachable, Lost, Invalid; each has its own required inputs (document 04 §4.2) | P1 | v-03 §7.1 | Not built |
| FR-WORK-05 | Choosing Follow-up requires date and time, completes the batch item and places the lead in the follow-up queue | P1 | v-03 §7.2 | Schema only |
| FR-WORK-06 | A due follow-up appears in Follow-up due; recording feedback on it closes it | P1 | v-01 §15 | Not built |
| FR-WORK-07 | Lead status changes only through allowed transitions, checked on the server | P1 | Interim | Not built |
| FR-WORK-08 | The owner can correct the student name and add or remove contact numbers; every change is recorded | P2 | Interim | Not built |
| FR-WORK-09 | The owner can mark a lead High, Medium or Low priority; tasks sort by it within a section | P3 | Interim | Not built |
| FR-WORK-10 | "Past inquiries and course history" on an owned lead shows other leads with the same numbers | P1 | v-03 §12 | Not built |
| FR-WORK-11 | Every lead shows its full timeline in order | P1 | v-01 §25 | Not built |
| FR-WORK-12 | An executive has no list, search or export of leads they do not own | P1 | all | Not built — the opposite is true today |

### M10 — Overdue, Lazy and reassignment

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-OVR-01 | A follow-up past its time is Overdue; overdue items are shown to the owner, TL and supervisor | P1 | v-01 §16 | Partial — counters only |
| FR-OVR-02 | A batch lead with no first action within a configured time is flagged Unworked | P2 | v-01 §21, Q-14 | Not built |
| FR-OVR-03 | An executive becomes Lazy and receives no new batch when a follow-up is still open at the end of their next working shift after it fell due, or when their batch has been unfinished for two working days | P1 | v-01 §10, v-03 §8, D-4 | Not built |
| FR-OVR-04 | Lazy clears when neither condition holds, or when a TL, supervisor or admin clears it with a reason | P1 | v-03 §4.3 | Not built |
| FR-OVR-05 | TL and supervisor are alerted when an executive turns Lazy | P2 | v-03 §8 | Not built |
| FR-OVR-06 | TL, supervisor and admin reassign leads within their reach, with a reason; recorded in the timeline | P1 | v-01 §22 | Not built |
| FR-OVR-07 | When an executive is deactivated: unworked leads return to the central queue; worked leads go to a reassignment list for their TL | P1 | v-01 §28, Q-11 | Not built |
| FR-OVR-08 | Admin and TL reopen a closed lead with a reason; who, when and why are recorded | P2 | v-01 §36, Q-14 | Not built |

### M11 — Transfers

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-TRF-01 | The owner requests a transfer to a named executive with a mandatory reason | P2 | all | Schema only |
| FR-TRF-02 | A transfer within one team needs that team leader's approval | P2 | v-01 §19, Q-08 | Not built |
| FR-TRF-03 | A transfer between teams needs both team leaders' approval | P2 | v-03 §11 | Not built |
| FR-TRF-04 | While pending, the lead stays with the current owner and is fully workable | P2 | v-03 §11 | Not built |
| FR-TRF-05 | Either approver can reject with a reason; the requester can cancel | P2 | v-03 §11 | Not built |
| FR-TRF-06 | A request pending for the configured number of days is approved and executed automatically | P2 | v-03 §11 | Not built |
| FR-TRF-07 | An admin transfer takes effect immediately without approvals | P2 | db.md §2.7 | Not built |
| FR-TRF-08 | On execution: owner and team change, open follow-ups move, the previous owner keeps read-only access to the history, the timeline records it | P2 | v-03 §11 | Not built |
| FR-TRF-09 | One pending transfer per lead; transfer inbox for approvers with age and auto-approval date | P2 | Mockup | Not built |

### M12 — Conversion and enrollment

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-ENR-01 | Only the owner converts a lead, by choosing Converted in feedback | P1 | v-03 §9 | Not built |
| FR-ENR-02 | Enrollment form: student name, age, background, guardian name and contact, course, base fee, discount, final amount | P1 | v-02 §13 | Partial — the action accepts some fields; no screen |
| FR-ENR-03 | The enrollment reserves a seat in the lowest-numbered batch with free capacity, safely under concurrent use; a full course blocks conversion | P1 | v-03 §9.3 | Partial — logic built and tested, not reachable |
| FR-ENR-04 | Installment plan: number of installments, each with amount and due date; amounts total the final amount | P1 | v-02 §13 | Schema only |
| FR-ENR-05 | A unique unguessable link is created for the customer | P1 | v-02 §13 | Not built |
| FR-ENR-06 | Lead status becomes "Enrolled – Pending Payment"; the lead keeps its batch slot until Accounts verifies the first payment | P1 | v-03 §9.1, D-3 | Not built |
| FR-ENR-07 | Discounts above a configured limit need approval | P2 | New, Q-15 | Not built |
| FR-ENR-08 | An enrollment can be cancelled with a reason; the seat is released; the lead returns to its owner | P2 | v-03 §9.3 | Not built |
| FR-ENR-09 | The executive copies the link or sends it by SMS or WhatsApp from the system | P2 | v-03 §9, Q-16 | Not built |

### M13 — Customer page and payments

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-PAY-01 | The link opens a page without sign-in, showing only that enrollment | P1 | v-02 §13 | Not built |
| FR-PAY-02 | The customer can correct the spelling of name and email, and contact the executive about anything else | P2 | v-02 §13 | Not built |
| FR-PAY-03 | The page shows course, fee, discount, installment plan and the amount due now | P1 | v-02 §13 | Not built |
| FR-PAY-04 | Manual payment: channel, sender number or account, transaction ID and/or slip image — at least one of the two | P1 | v-03 §10 | Schema only |
| FR-PAY-05 | Slip images are type and size checked and stored where the public cannot reach them | P1 | New | Not built |
| FR-PAY-06 | bKash checkout with server-side confirmation | P3 | v-03 §10 | Not built |
| FR-PAY-07 | SSLCommerz checkout with validation callback | P3 | v-03 §10 | Not built |
| FR-PAY-08 | A submitted payment sets enrollment and lead to "Payment Under Review" | P1 | v-03 §9.1 | Not built |
| FR-PAY-09 | The page is protected: request limits, link expiry, no listing of other records, a transaction ID cannot be used twice | P1 | New | Not built |

### M14 — Accounts and installments

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-ACC-01 | Accounts queue of payments under review with age, amount, method and proof | P1 | v-03 §9 | Not built |
| FR-ACC-02 | Accounts verifies a payment: the installment becomes Paid, the enrollment Enrolled, the lead Converted | P1 | v-03 §9.1 | Not built |
| FR-ACC-03 | Accounts rejects with a reason: the executive is notified and the customer can submit again | P1 | v-03 §9 | Not built |
| FR-ACC-04 | A numbered money receipt is produced for each verified payment and can be downloaded and sent | P2 | v-03 §9 | Not built |
| FR-ACC-05 | Each installment has a status: Pending, Under Review, Paid, Overdue | P1 | v-03 | Schema only |
| FR-ACC-06 | The configured number of days before a due date, an installment task appears in the executive's follow-ups | P2 | v-03 §9.2 | Not built |
| FR-ACC-07 | An installment past its due date becomes Overdue and is reported | P2 | v-03 | Not built |
| FR-ACC-08 | When the last installment is verified the enrollment is marked fully paid | P2 | v-03 §9.2 | Not built |

### M15 — Notifications

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-NTF-01 | In-app notification list with unread count for every user | P2 | v-01 §33 | Not built |
| FR-NTF-02 | Events notified per role as listed in document 04 §8 | P2 | v-01 §33 | Not built |
| FR-NTF-03 | Customer messages by SMS, WhatsApp or email: payment link, receipt, installment reminder | P3 | v-03 §9, Q-16 | Not built |
| FR-NTF-04 | Due and overdue follow-up reminders reach the executive during their shift | P2 | v-01 §16 | Not built |

### M16 — Dashboards and reports

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-RPT-01 | Admin dashboard: queue, active batches, overdue follow-ups, Lazy count, payments under review, pending transfers, conversions and amount, pipeline, roster, action inbox | P1 | Mockup, v-01 §29 | Partial — queue figures wrongly date-limited; inbox missing |
| FR-RPT-02 | Supervisor and TL dashboards with the same content limited to their teams | P1 | v-01 §29 | Not built |
| FR-RPT-03 | Sales executive dashboard: own batch, due and overdue follow-ups, own results | P1 | v-01 §12 | Not built |
| FR-RPT-04 | Accounts dashboard: review queue, verified today, installments due and overdue | P2 | v-03 | Not built |
| FR-RPT-05 | Executive KPIs: leads received, first-call completion, feedback completion, follow-up completion, overdue, interested, conversions, conversion rate | P2 | v-01 §30 | Not built |
| FR-RPT-06 | Team comparison and performance over a date range | P2 | v-01 §29 | Partial — 30-day conversion only |
| FR-RPT-07 | Source and campaign report | P2 | v-01 §31 | Not built |
| FR-RPT-08 | Management lead list with search, the v-01 §32 filters and export, limited to the viewer's reach | P1 | v-01 §32 | Partial — works, but with no limit by role |

### M17 — Settings and administration

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-SET-01 | Admin-only settings screen | P1 | v-03 §3 | Built |
| FR-SET-02 | Settings, each typed and range-checked: engine switch, batch size, cut-off minutes, Lazy threshold, transfer auto-approval days, installment lead days | P1 | v-03 §3 | Partial — two of six |
| FR-SET-03 | The engine and the jobs read these settings; a change applies from the next run | P1 | v-03 §3 | Not built |
| FR-SET-04 | The application runs in the Asia/Dhaka time zone | P1 | New | Not built |
| FR-SET-05 | The interface is available in Bangla and English | P2 | Interim, Q-18 | Not built |

### M18 — History and audit

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-HIS-01 | Every event on a lead is appended to its timeline: created, assigned, feedback, follow-up, transfer, reassignment, status change, reopen, enrollment, payment | P1 | v-01 §24–25 | Not built |
| FR-HIS-02 | Feedback, timeline, transfer and payment records can never be edited or deleted, by any role | P1 | all | Not built |
| FR-HIS-03 | Master data records who last edited them and when | P2 | db.md | Partial — on five tables |
| FR-HIS-04 | Admin actions are written to an audit log: role and status changes, settings, exports, password decryption, overrides | P1 | New | Not built |
| FR-HIS-05 | A mistake in history is corrected by adding a correction note, never by changing the original | P2 | v-01 §37 | Not built |

### M19 — Migration from the interim system

| ID | Requirement | Pri | Source | Status |
| :-- | :--- | :-- | :--- | :--- |
| FR-MIG-01 | Staff, campaigns and courses of the sheet are mapped to ERP records before lead data is loaded | P1 | New | Not built |
| FR-MIG-02 | Contacts become leads, inquiries become inquiry records, activities become feedback and timeline entries, keeping dates and owners | P1 | New | Not built |
| FR-MIG-03 | The migration can be rehearsed and produces a report of counts, rejects and duplicates | P1 | New | Not built |
| FR-MIG-04 | A cut-over plan: freeze the sheet, migrate, verify, switch staff, keep the sheet read-only | P1 | New | Not built |

---

## 4. Non-functional requirements

| ID | Category | Requirement |
| :-- | :--- | :--- |
| NFR-01 | Security | Every request is authorised on the server by role and by ownership; guessing a record ID returns "forbidden" |
| NFR-02 | Security | No customer data, credential or key is stored in a web-served folder or in source control |
| NFR-03 | Security | Staff passwords use the reversible method decided in D-1. Its key is held in server configuration, never in code, a document or the repository; stored values are never displayed or exported; database and backup access is restricted accordingly |
| NFR-04 | Security | Uploaded files are validated and stored privately; file names are never trusted |
| NFR-05 | Security | Exports are limited by role, logged, and protected against spreadsheet formula injection |
| NFR-06 | Security | Production runs with debug off, HTTPS only, secure cookies |
| NFR-07 | Privacy | Phone numbers of leads are visible only to the owner and the management chain above them |
| NFR-08 | Integrity | Every multi-step change (assignment, feedback, transfer, conversion, verification) is one database transaction |
| NFR-09 | Integrity | One lead never has two active owners, two open batch items, or two pending transfers — guaranteed by the database |
| NFR-10 | Integrity | Money is stored as fixed decimals and never computed in floating point |
| NFR-11 | Performance | List pages answer within 1 second at 100,000 leads; each page runs a bounded number of queries |
| NFR-12 | Performance | A distribution run for 50 executives and 500 queued leads completes within 5 seconds |
| NFR-13 | Reliability | Scheduled jobs are safe to run twice and safe to miss a run |
| NFR-14 | Reliability | A failed engine run changes nothing and is retried |
| NFR-15 | Auditability | Every state change records who, when and from what to what |
| NFR-16 | Usability | The sales workspace works on a phone at 360 px width |
| NFR-17 | Usability | Interface language per Q-18; Bangla text renders in a Bengali font |
| NFR-18 | Maintainability | Statuses, roles and channels are defined once (PHP enums) and used everywhere |
| NFR-19 | Maintainability | Business rules live in services with tests; controllers only validate, authorise and call them |
| NFR-20 | Testability | Each functional requirement has at least one automated test; engine rules have table-driven tests |
| NFR-21 | Testability | The test suite also runs against MySQL before a release |
| NFR-22 | Operations | Daily database backup with a tested restore |
| NFR-23 | Operations | Engine runs, job runs and failures are logged and visible to Admin |
| NFR-24 | Time | All business time rules use Asia/Dhaka; timestamps are stored consistently |

---

## 5. External interfaces

| Interface | Direction | Purpose | Phase |
| :--- | :--- | :--- | :-- |
| Browser | In | Staff application and customer payment page | 1 |
| CSV / Excel files | In | Lead upload | 1 |
| CSV files | Out | Exports and error rows | 1 |
| PDF | Out | Money receipt | 3 |
| SMS / WhatsApp / email provider | Out | Payment link, receipt, reminders | 3–4 |
| bKash | Both | Checkout and confirmation | 4 |
| SSLCommerz | Both | Checkout and validation | 4 |
| Scheduler (cron) | Internal | Engine tick, overdue check, transfer expiry, installment reminders | 1–3 |

---

## 6. Acceptance criteria

The v-01 list of twelve, the v-03 list of ten, and the additions this analysis requires. Each must be demonstrated by an automated test.

| # | Criterion | Requirements |
| :-- | :--- | :--- |
| AC-01 | An executive never holds more than one active batch, and a batch never exceeds n | FR-DIST-05, -10 |
| AC-02 | No new batch arrives until every item of the current one is completed | FR-DIST-05 |
| AC-03 | No batch is issued after an executive's cut-off; queued leads wait and none is lost | FR-DIST-09 |
| AC-04 | With L leads and S executives the allocation matches document 04 §3 for abundant, moderate and severe scarcity | FR-DIST-06 |
| AC-05 | Turning the engine off stops automatic batches within one cycle | FR-DIST-02 |
| AC-06 | Scheduling a follow-up frees the batch slot at once | FR-WORK-05 |
| AC-07 | A follow-up appears in the owner's due list at its time | FR-WORK-06 |
| AC-08 | Feedback is required to complete a task; a call alone completes nothing | FR-WORK-03 |
| AC-09 | An executive cannot open, list, search or export a lead they do not own — by screen or by URL | FR-WORK-12, NFR-01 |
| AC-10 | A team leader cannot reach another team's leads or settings; a supervisor cannot reach unassigned teams | FR-EMP-08 |
| AC-11 | A follow-up overdue past the next working shift, or a batch unfinished for two working days, makes the executive Lazy and stops new batches; clearing the cause restores them; a lead waiting for payment alone never makes an executive Lazy | FR-OVR-03, -04 |
| AC-12 | A cross-team transfer executes only after both approvals, or after the configured days, and the lead is workable throughout | FR-TRF-03, -04, -06 |
| AC-13 | After a transfer the new owner sees the full history and the old owner can no longer act | FR-TRF-08 |
| AC-14 | The same number in any accepted format matches the same lead | FR-ING-04 |
| AC-15 | The same phone with a different course or student reaches the review queue; an exact repeat creates no lead | FR-DUP-02, -03 |
| AC-16 | Conversion creates an enrollment, a seat reservation, an installment plan and a link in one step, or nothing | FR-ENR-02 to -05 |
| AC-17 | A manual payment without both a transaction ID and a slip is refused | FR-PAY-04 |
| AC-18 | Verification by Accounts completes the sale, frees the executive's batch slot and produces a receipt; rejection returns it with a reason and the slot stays occupied | FR-ACC-02 to -04, FR-ENR-06 |
| AC-19 | An installment task appears the configured days before its due date, once | FR-ACC-06 |
| AC-20 | No role can edit or delete feedback, timeline, transfer or payment records | FR-HIS-02 |
| AC-21 | A suspended employee loses access on their next request | FR-AUTH-03 |
| AC-22 | No employee can raise their own role | FR-EMP-05 |
| AC-23 | Deactivating an executive leaves none of their leads without an owner or a queue | FR-OVR-07 |
| AC-24 | After migration, lead, inquiry and activity counts match the sheet, with every difference listed | FR-MIG-03 |
