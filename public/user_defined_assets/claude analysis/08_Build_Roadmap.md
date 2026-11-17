# 08 — Build Roadmap

The order in which to build, what each phase needs before it starts, and how to know it is finished. Sizes are relative (S, M, L, XL), not calendar estimates.

---

<!-- build-update -->

## Build update — 17 November 2026

| Item | Status |
| :--- | :--- |
| 0.1 Secure the data folder | **Not done — owner action** |
| 0.2 Time zone | Done |
| 0.3 Enums | Not done |
| 0.4 Policies and scoping | Scoping done in controllers and model; policy classes not written |
| 0.5 Role requests, last admin | Done |
| 0.6 Status check on every request | Done |
| 0.7 Reversible passwords, read and reset screen | Done; existing accounts convert at next sign-in instead of needing new passwords |
| 0.8 Phone normaliser | Not done |
| 0.9 One intake service | Not done; quick-create crash fixed |
| 0.10 History and audit tables | Not done |
| 0.11 Dashboard figures, export | Dashboard done; export scoped by role but not logged |
| 0.12 Tests for verified defects | Done for the repaired defects |
| 0.13 README and document corrections | README done; `db.md` and the v-03 checklist not corrected |
| 1.3 Settings, engine switch | Done |
| 1.4 Scheduler | Defined in code; must be started on the server |
| 1.5–1.7 Assignment, engine, central queue | Done, plus priority ranking; no run log, no database guards |
| 1.8–1.9 Tasks, lead page, feedback, follow-ups | Done |
| 1.10–1.12 Name correction, inquiry log, import improvements | Not done |
| 1.13 Dashboards | Done for five roles |
| 1.14–1.15 Migration and cut-over | Not done |
| 2.1 Overdue and Lazy | Lazy from overdue follow-ups only |
| 2.2 Reassignment, return to queue | Done for follow-up leads |
| 2.4 Transfers | Done |
| 2.5 Notifications | Not done |
| 2.6 Supervisor reach | Done for dashboard, leads, follow-ups, teams |
| 2.7 Reports | Admin reports page done; no export |
| 3.4 Accounts queue, verify, reject | Done |
| 3.6 Installment views | Views done; reminders and status job not done |
| 3.1–3.3, 3.5, 3.7 Enrollment form, customer page, manual payment, receipts, cancellation | Not done |
| Phase 4 | Not started |

Recommended next: 0.1, then the enrollment form (3.1), then phone normalisation (0.8) and the history table (0.10).

<!-- build-update -->


## 1. Overview

| Phase | Goal | Result for the business | Size |
| :-- | :--- | :--- | :-- |
| 0 | Secure and stabilise | What exists is safe to use and safe to build on | M |
| 1 | Core sales loop | The team works in the ERP; the Google Sheet is retired | XL |
| 2 | Control | Management can enforce follow-up discipline and move leads properly | L |
| 3 | Conversion and finance | Enrollment, installments and payment verification run in the ERP | L |
| 4 | Integrations and polish | Online payment, automatic messages, Bangla interface | M |

Phases are sequential. Phase 0 must not be skipped: every later feature depends on the permission layer, the phone rule and the history table it introduces.

```
Phase 0 ──▶ Phase 1 ──▶ Phase 2 ──▶ Phase 3 ──▶ Phase 4
 security     engine      Lazy        enrollment   gateways
 policies     tasks       transfers   payments     messaging
 phones       feedback    reports     accounts     language
 history      migration
```

---

## 2. Phase 0 — Secure and stabilise

**Needs first:** Q-17 (folders and packages). The password method is decided. Step 0.1 needs no decision and should happen today.

| # | Work | Fixes | Size |
| :-- | :--- | :--- | :-- |
| 0.1 | Change interim PINs; check repository visibility; move `user_defined_assets` out of `public/`; purge data files from Git history | W-SEC-01 | S |
| 0.2 | Set the time zone to Asia/Dhaka | W-BUG-09 | S |
| 0.3 | PHP enums for role, employee status, lead status, channel, call status; cast on models | W-ARCH-03 | M |
| 0.4 | Policies for every model and the permission matrix; scope lead and team queries by role | W-SEC-02, -04, -09 | L |
| 0.5 | Remove role and status from self-service requests; approver must outrank requested role; keep at least one admin | W-SEC-03, -08 | S |
| 0.6 | Check employee status on every request; end sessions on status change | W-SEC-05 | S |
| 0.7 | Reversible password storage by the PDF formula with a new key in server configuration (document 04 §16); admin decrypt screen with audit log; password rules; admin reset to a temporary password; new temporary passwords for all existing accounts | W-SEC-06, -07, FR-AUTH-08, FR-AUTH-09 | M |
| 0.8 | Phone normaliser; `lead_phones` table; one-time clean-up of stored numbers | W-BUG-02 | M |
| 0.9 | One lead-intake service used by quick-create, single entry, import and review approval; campaign's course applied; no campaign creation from lead screens | W-BUG-01, -03 (basic), -05, -12 | M |
| 0.10 | `lead_activities` and `audit_logs` tables; write history from intake and assignment | W-ARCH-04, W-SEC-10 | M |
| 0.11 | Dashboard queue figures independent of the date range; export limited, logged and made safe | W-BUG-07, W-SEC-11 | S |
| 0.12 | A failing test for each verified defect, then the fix; run the suite on MySQL as well | W-QA-01, -02 | M |
| 0.13 | Real README; correct `db.md`; reset the v-03 checklist | W-OPS-02 | S |

**Finished when**

- No customer data, PIN or key is in `public/` or in the repository history.
- Each of the eight verified defects has a test that failed before and passes now.
- A sales executive receives "forbidden" on `/leads`, on export, and on another executive's lead by URL.
- A team leader cannot change another team; no employee can raise their own role; a suspended employee is signed out on the next click.
- The same number in `+880`, `880`, `0` and ten-digit form matches one lead.

---

## 3. Phase 1 — Core sales loop

**Needs first:** Q-05, Q-06, Q-07, Q-10, Q-12, Q-13, Q-19. Repeat-inquiry handling is decided.

| # | Work | Requirements | Size |
| :-- | :--- | :--- | :-- |
| 1.1 | Shift screen with names and working days; per-date overrides without typed statuses | FR-TEAM-05, -07, -08 | M |
| 1.2 | Check-in; computed availability used by every screen | FR-TEAM-09, -10, -11 | M |
| 1.3 | Remaining settings with types; engine switch | FR-SET-02, -03 | S |
| 1.4 | Scheduler and queue worker in the deployment | — | S |
| 1.5 | Assignment service; batch tables with database guards | FR-DIST-04, -05, -10 | M |
| 1.6 | Distribution engine: queue rules, allocation, triggers, run log | FR-DIST-02, -03, -06 to -09, -12 | L |
| 1.7 | Central queue screen and manual assignment through the service | FR-DIST-01, -11 | M |
| 1.8 | My Tasks; lead page; timeline | FR-WORK-01, -02, -11, -12 | L |
| 1.9 | Feedback, next-action rules, status transitions, follow-up queue | FR-WORK-03 to -07 | L |
| 1.10 | Name correction, extra numbers, priority, past-inquiries view | FR-WORK-08 to -10 | M |
| 1.11 | Inquiry log; duplicate rules D0–D8 | FR-ING-09, FR-DUP-01 to -03, -08 | L |
| 1.12 | Import with result summary, partial import, error file, Excel, background runs | FR-ING-01, -02, -08 | M |
| 1.13 | Dashboards for sales executive, team leader and admin (operational figures) | FR-RPT-01 to -03 | M |
| 1.14 | Migration scripts, rehearsal and report | FR-MIG-01 to -03 | L |
| 1.15 | Cut-over | FR-MIG-04 | S |

**Build order inside the phase:** 1.1–1.4 → 1.5–1.7 → 1.8–1.10 → 1.11–1.12 → 1.13 → 1.14–1.15. The engine can be built and tested with the switch off while staff still use the sheet.

**Finished when**

- Acceptance criteria AC-01 to AC-09, AC-14, AC-15 and AC-24 pass as automated tests.
- The engine's allocation matches the table in document 04 §3.2 for every row.
- A rehearsal migration reproduces the sheet's counts, with every difference explained.
- The team has worked one full week in the ERP with the sheet read-only.

---

## 4. Phase 2 — Control

**Needs first:** Q-04a, Q-08, Q-11, Q-14. The Lazy rule is decided, apart from its treatment of leads awaiting payment.

| # | Work | Requirements | Size |
| :-- | :--- | :--- | :-- |
| 2.1 | Overdue and unworked detection; Lazy flag on either condition, recovery, manual clearing | FR-OVR-01 to -05 | M |
| 2.2 | Reassignment, return to queue, reopen | FR-OVR-06, -08 | M |
| 2.3 | Deactivation and resignation handling | FR-EMP-09, FR-OVR-07 | M |
| 2.4 | Transfers: request, approvals, rejection, auto-approval, admin override, inbox | FR-TRF-01 to -09 | L |
| 2.5 | In-app notifications for the events in document 04 §8 | FR-NTF-01, -02, -04 | M |
| 2.6 | Supervisor reach across assigned teams on every screen | FR-EMP-08, FR-RPT-02 | M |
| 2.7 | Executive KPIs, team comparison, campaign report, management lead list | FR-RPT-05 to -08, FR-CMP-05 | L |
| 2.8 | Duplicate review: edit-and-dispatch; supervisor access | FR-DUP-06, -07 | S |
| 2.9 | Immutability triggers and model guards; correction notes | FR-HIS-02, -05 | S |

**Finished when** AC-10 to AC-13 and AC-20 to AC-23 pass.

---

## 5. Phase 3 — Conversion and finance

**Needs first:** Q-15, Q-16. The batch-slot rule is decided: held until Accounts verifies.

| # | Work | Requirements | Size |
| :-- | :--- | :--- | :-- |
| 3.1 | Enrollment form with amounts, discount rule, installment plan; correct the existing action | FR-ENR-01 to -07 | L |
| 3.2 | Customer page: review, correction, amount due | FR-PAY-01 to -03, -09 | M |
| 3.3 | Manual payment with private slip storage | FR-PAY-04, -05, -08 | M |
| 3.4 | Accounts queue with waiting time and alert; verify (completes the batch item and triggers the engine); reject | FR-ACC-01 to -03, FR-ENR-06 | M |
| 3.5 | Money receipt with gap-free numbering (PDF) | FR-ACC-04 | M |
| 3.6 | Installment statuses, reminders, overdue, fully paid | FR-ACC-05 to -08 | M |
| 3.7 | Enrollment cancellation and seat release; unpaid-enrollment alerts | FR-ENR-08 | S |
| 3.8 | Accounts dashboard and finance figures on the admin dashboard | FR-RPT-04 | S |

**Finished when** AC-16 to AC-19 pass, and one real enrollment has gone from conversion to receipt in the ERP.

---

## 6. Phase 4 — Integrations and polish

**Needs first:** merchant accounts, a messaging provider, Q-18.

| # | Work | Requirements | Size |
| :-- | :--- | :--- | :-- |
| 4.1 | bKash checkout and confirmation | FR-PAY-06 | M |
| 4.2 | SSLCommerz checkout and validation | FR-PAY-07 | M |
| 4.3 | SMS or WhatsApp for payment link, receipt and reminders | FR-NTF-03, FR-ENR-09 | M |
| 4.4 | Bangla interface | FR-SET-05 | M |
| 4.5 | Later ideas from v-01 phase 3: automatic reassignment, lead scoring, advanced analytics | — | L |

---

## 7. Decisions needed, by phase

| Before | Questions |
| :--- | :--- |
| Phase 0 | Q-17, Q-20 |
| Phase 1 | Q-05, Q-06, Q-07, Q-10, Q-12, Q-13, Q-19 |
| Phase 2 | Q-04a, Q-08, Q-09, Q-11, Q-14 |
| Phase 3 | Q-15, Q-16 |
| Phase 4 | Q-18 |

Q-01 to Q-04 were decided on 16 November 2026 (document 09, Part 1). Their effect on table design is already in document 05, so Phase 1 can build the batch tables once.

---

## 8. How to work

1. **One rule, one test, first.** Each business rule in document 04 gets a test before its code. The engine and duplicate rules suit table-driven tests: inputs and expected results side by side.
2. **Rules in services, not controllers.** A controller validates, authorises, calls one service, returns. This is what lets the same rule serve the upload, the form and the migration.
3. **Every change to a lead goes through a service that writes the timeline.** No direct `update` on a lead anywhere else.
4. **Database guards for the rules that must never break** (document 05 §4), so a bug cannot create two owners.
5. **Small changes, each with its tests, formatted with Pint**, as `AGENTS.md` already requires.
6. **Keep the engine switch off in production until cut-over.** Everything can be deployed and rehearsed without affecting the sheet.
7. **Run the suite on MySQL before each release**, not only on SQLite.
8. **Update the documents in the same change as the code** — the gap between v-03's checklist and reality came from letting them drift.

---

## 9. Main risks to the plan

| Risk | Effect | Mitigation |
| :--- | :--- | :--- |
| Open questions stay open | Engine built on assumptions, then reworked | Answer Q-04a to Q-07 before Phase 1; the defaults in document 09 are usable as they are |
| The batch slot is held until payment is verified | Sales capacity depends on how fast Accounts works | Agree a verification target time before Phase 3; show waiting time on the Accounts queue |
| Passwords become readable | A database or key leak exposes every password | Apply the seven measures of W-SEC-07 in Phase 0, before the first real account is created |
| Staff login phones are not in the sheet | Migration of staff blocked | Collect them during Phase 0 |
| Hosting cannot run a scheduler or queue worker | Engine, reminders and Lazy rule cannot run | Confirm hosting in Q-20 before Phase 1 |
| Cut-over during working hours | Two systems in use at once | Freeze the sheet at close of business; go live next morning |
| Excel, PDF and gateway packages need approval | Import, receipts and payments delayed | Decide the package list once in Q-17 |
| Phase 3 started before Phase 2 | Money handled without audit, reassignment or deactivation rules | Keep the order |
