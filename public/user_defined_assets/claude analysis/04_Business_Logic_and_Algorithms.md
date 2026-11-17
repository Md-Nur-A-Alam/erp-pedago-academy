# 04 — Business Logic and Algorithms

The exact rules the system must apply. Where a rule depends on an unanswered question, the recommended default is written out and the question number is given, so the build can start and the rule can be switched later.

Four questions were decided on 16 November 2026 and are built into the rules below: reversible passwords by the PDF formula (§16), automatic handling of repeat inquiries (§7), the batch slot of a converted lead held until Accounts verifies payment (§4.2, §11.3), and the two-condition Lazy rule (§9).

---

<!-- build-update -->

## Build update — 17 November 2026

The rules in this document are now largely implemented. Three points differ from, or add to, the text below; the code is the reference for these.

### 17. Lead priority and call order (new)

Research on lead response is consistent: the odds of reaching and qualifying a person are highest in the first minutes after the inquiry and fall steeply within the first hour, then more slowly over the following days. The engine therefore serves freshness first, while guaranteeing that old leads are still called.

| Temperature | Age since received | Setting (default) |
| :--- | :--- | :--- |
| Hot | up to the hot window | `hot_lead_minutes` (60) |
| Warm | up to the warm window | `warm_lead_hours` (24) |
| Cool | up to the cold threshold | `cold_lead_hours` (72) |
| Cold | older | — |

**Choosing leads for a cycle of N slots**

1. Fresh = hot and warm leads in the auto queue, newest first. Stale = cool and cold.
2. If fresh leads exist, reserve `floor(N / 4)` slots (never more than the stale count) for the **oldest** stale leads.
3. Fill the other slots with fresh leads; if there are too few, fill with the remaining leads, newest first.
4. Order the chosen leads by rank: temperature (hot, warm, cool, cold), then high-intent channel (phone, reference, WhatsApp, website, Google form) before others, then newest first.
5. Deal them one at a time, in rounds, to the available executives in longest-idle order. The hottest leads are therefore spread across executives, and each executive's first lead is their hottest.

**Executive's task list ("Call next")**

| Order | What | Why |
| :-- | :--- | :--- |
| 1 | Hot new leads | Minutes matter most here |
| 2 | Call-backs that are due, interested customers first, then longest overdue | A kept promise builds trust; a broken one loses the sale |
| 3 | Warm, then cool, then cold new leads, high-intent channel first | Remaining value falls with age |
| 4 | Call-backs due later today | Shown so the day can be planned |

**Team leader's view** — the roster is sorted Lazy first, then by overdue follow-ups, then by uncalled leads; a separate list shows assigned leads with no first call, longest wait first.

Not implemented, by choice: routing the hottest leads to the best converters. It raises short-term conversion but concentrates opportunity and slows new executives' learning; the fair round-robin is kept until the owner decides otherwise (Q-22).

### §16 as implemented

The stored value is `pdg1$` followed by the lowercase hex of the documented procedure. The prefix lets the system tell a readable password from an older bcrypt hash. An account with a bcrypt hash still signs in, and is converted at that sign-in. The key is read from `STAFF_PASSWORD_KEY`; if it is empty the system falls back to one-way hashing and the read screen says so.

### Differences from the text below

- **Lazy** is raised by an overdue follow-up only. The second condition decided in Q-04 (an unfinished batch) is not implemented, so Q-04a does not arise yet.
- **Next actions** on feedback are Follow-up, Interested, Not Interested, Unreachable and Lost. There is no enrollment action because the enrollment form does not exist.
- **Giving feedback frees the batch slot** for every next action. A lead already in enrollment keeps its slot until Accounts verifies.

<!-- build-update -->


## 1. Lead lifecycle

### 1.1 Statuses

| Status | Meaning | Owner | Open or closed |
| :--- | :--- | :-- | :-- |
| New | In the central queue, not yet assigned | None | Open |
| Assigned | In an executive's batch, no feedback yet | Yes | Open |
| Follow-up | Contact attempted or made; a next contact is scheduled | Yes | Open |
| Enrolled – Pending Payment | Enrollment created, customer has not paid | Yes | Open |
| Payment Under Review | Payment submitted, waiting for Accounts | Yes | Open |
| Converted | First payment verified | Yes | Closed (won) |
| Not Interested | Customer declined | Yes | Closed |
| Unreachable | Could not be contacted after the required attempts | Yes | Closed |
| Lost | Conversion is no longer possible, with a reason | Yes | Closed |
| Invalid | Wrong number, outside the age range, junk or test entry | Yes | Closed |

Changes from the current enum, to be confirmed in Q-13:

- **Invalid** is added. The interim system has 28 such leads and the specs have no home for "Wrong Number", "Under Age" and "Over Age".
- **Contacted** and **Interested** stop being statuses. No next action in v-03 leads to either. They become facts on the lead: `first_contacted_at` and `interest_level` (the latest customer interest). The dashboard's "Interested" bar counts open leads whose interest level is Interested or Highly Interested.
- "Fully Completed" and "Converted (Installment)" from v-03 §9.1 are not lead statuses. The lead is Converted; the enrollment shows whether installments are still running.

### 1.2 Allowed transitions

| From | To | Triggered by |
| :--- | :--- | :--- |
| — | New | Upload, single entry, approved duplicate review |
| New | Assigned | Distribution engine or manual assignment |
| Assigned | Follow-up / Not Interested / Unreachable / Lost / Invalid | Feedback by the owner |
| Assigned | Enrolled – Pending Payment | Feedback "Converted" plus enrollment form |
| Follow-up | Follow-up | Feedback rescheduling the next contact |
| Follow-up | Not Interested / Unreachable / Lost / Invalid | Feedback |
| Follow-up | Enrolled – Pending Payment | Feedback "Converted" plus enrollment form |
| Enrolled – Pending Payment | Payment Under Review | Customer submits a payment |
| Payment Under Review | Converted | Accounts verifies |
| Payment Under Review | Enrolled – Pending Payment | Accounts rejects |
| Enrolled – Pending Payment | Follow-up | Enrollment cancelled |
| Assigned / Follow-up | New | Requeue: owner deactivated, or TL returns the lead to the queue |
| Any closed status | Follow-up | Reopen by Admin or TL, or automatic reactivation on a new inquiry |

Any other change is refused on the server. Feedback can never set a lead back to New or Assigned — the interim system once stuck several executives' batches exactly this way.

### 1.3 Ownership rules

1. `current_owner_id` is the single source of truth for ownership.
2. Every ownership change writes `previous_owner_id`, `assigned_by_employee_id`, `assigned_at` and a timeline entry.
3. `team_id` on a lead always equals the owner's team at the time of assignment and is updated on transfer and reassignment (assumption A-2).
4. Only employees with the Sales Executive role can own leads (assumption A-5, Q-10).
5. A lead in the central queue has no owner and no team.

---

## 2. Availability

### 2.1 What is stored and what is computed

The current schema stores an availability word on per-date roster rows that exist only when someone creates an override. That cannot drive an engine. The target design stores only what a person decides and computes the rest.

| Stored | Where | Set by |
| :--- | :--- | :--- |
| Employment status: Active, Inactive, Suspended, Resigned | `employees.status` | Admin |
| Team membership, default shift | `employees` | Admin |
| Shift times and working days | `shifts` | Admin |
| Per-date override: off duty, or another shift | `employee_rosters` | Admin, TL |
| Check-in time today | `employees.checked_in_at` | The executive |
| Paused flag with reason | `employees.paused_at`, `paused_by`, `pause_reason` | TL, Supervisor, Admin |
| Lazy since, grace until | `employees.lazy_since`, `lazy_grace_until` | System; cleared by management |
| Idle since | `employees.idle_since` | System |

### 2.2 Eligibility to receive a batch

An executive is eligible at time *now* only if **all** hold:

| # | Condition |
| :-- | :--- |
| E1 | Role is Sales Executive and employment status is Active |
| E2 | Belongs to a team that is active |
| E3 | Today is a working day of the effective shift, with no off-duty override or leave |
| E4 | Has checked in today (Q-06) |
| E5 | shift start ≤ now ≤ shift end − cut-off minutes |
| E6 | Is not paused |
| E7 | Is not Lazy, or is inside a grace period |
| E8 | Has no active batch — zero incomplete batch items |

Effective shift: date override → team shift → personal default shift (already implemented in `Employee::effectiveShift`).

Overnight shifts: when the end time is earlier than the start time, the end belongs to the next calendar day, and the working date is the date the shift starts.

### 2.3 Displayed status

One word per executive, by first match:

| Order | Status | When |
| :-- | :--- | :--- |
| 1 | Resigned | Employment status is Resigned |
| 2 | Off Duty | E3, E4 or E5 fails |
| 3 | Lazy | E7 fails |
| 4 | Unavailable | E6 fails |
| 5 | Busy | E8 fails |
| 6 | Available | Everything holds |

One function computes this, and every screen and the engine use it. Today three places compute it three ways (W-BUG-08).

### 2.4 Check-in and check-out

- "Start my day" is offered from 30 minutes before shift start. It sets `checked_in_at` and `idle_since`.
- The system clears the check-in at shift end. No one needs to press a button to leave.
- Reason for requiring it: the interim system shows every staff member as online at all times because nobody logs out. A roster alone would hand leads to people who are not at their desk.

---

## 3. Distribution engine

### 3.1 The queue

A lead is distributable when all are true: status New; no owner; its campaign is active and in Auto mode, or it has no campaign; it is not waiting in duplicate review.

Leads of Manual campaigns stay in the queue for manual assignment and show the reason "Manual campaign".

**Order (Q-07):** campaign priority High, Normal, Low; then oldest first by creation time. Priority matters at go-live: about 1,200 old unassigned leads will be migrated, and a plain oldest-first rule would serve all of them before any fresh inquiry. Marking the historical campaign Low lets fresh leads go out first and the backlog fill the gaps.

### 3.2 Allocation rule

v-03 §6.2 describes three cases with the numbers 2 and 3 written in, while n is configurable. The three cases are one rule:

> **Deal one lead at a time to the eligible executives in round-robin order, longest idle first, until the leads run out or every executive holds n.**

In formula form, with L distributable leads, S eligible executives and batch size n:

```
base  = min(n, floor(L / S))
extra = (base < n) ? L mod S : 0
quota of the executive at position i (0-based, longest idle first) = base + (i < extra ? 1 : 0)
```

| L | S | n | Result | v-03 case |
| --: | --: | --: | :--- | :--- |
| 20 | 6 | 3 | 3 each; 2 leads stay queued | Abundant |
| 18 | 6 | 3 | 3 each | Abundant |
| 14 | 6 | 3 | First two get 3, the other four get 2 | Moderate scarcity (the mockup's example) |
| 12 | 6 | 3 | 2 each | Moderate scarcity |
| 4 | 6 | 3 | First four get 1; two get none | Severe scarcity |
| 14 | 4 | 5 | 4, 4, 3, 3 | Not defined in v-03; follows the same rule |

**Idle order:** earliest `idle_since` first; ties by fewest open leads, then by employee ID. `idle_since` is set at check-in and each time a batch completes.

### 3.3 Procedure

```
run(trigger):
    if setting lead_distribution_engine_active is false: stop
    take the application-wide lock "lead-distribution"; if unavailable, stop (the next tick retries)

    in one database transaction:
        S = eligible executives in idle order                      (rows locked)
        if S is empty: stop
        Q = first (n × |S|) distributable leads in queue order     (locked, skipping rows locked elsewhere)
        L = |Q|; if L is 0: stop
        compute each quota
        for each executive with quota > 0:
            create a batch (employee, size = quota, type Auto)
            for each of the next quota leads:
                lead: owner = executive, team = executive's team, status = Assigned,
                      assigned_at = now, assigned_by = null (system)
                create a batch item
                append "assigned" to the lead's timeline
            executive: idle_since = null
    write an engine-run record: trigger, L, S, n, quotas, duration
    notify each executive: "New batch assigned"
```

### 3.4 Triggers

| Trigger | When |
| :--- | :--- |
| Leads enter the queue | After an upload commits (once per upload, not once per row), single entry, approved review, requeue |
| An executive becomes eligible | Batch completed, check-in, pause removed, Lazy cleared |
| Engine switched on | Settings saved |
| Timer | Every minute — catches shift starts and anything a trigger missed |

Runs are queued jobs. A run that finds nothing to do ends immediately.

### 3.5 Rules and edge cases

| Situation | Rule |
| :--- | :--- |
| Batch completed after the cut-off | No new batch today; leads stay queued |
| Batch unfinished at shift end | It carries forward; the executive is Busy when they return |
| Fewer leads than n for one executive | The batch is created with the smaller size; it is not topped up later (Q-07) |
| Engine switched off | No automatic batches; manual assignment still works; existing batches continue |
| Two runs at once | The lock allows one; the second stops |
| A lead reassigned or closed while in a batch | Its batch item is completed with the matching action, so the batch can finish |
| A converted lead is waiting for payment | Its batch item stays open, so the executive is Busy and receives no new batch until Accounts verifies (decided, Q-03) |
| Manual assignment by Admin or TL | Goes through the same assignment routine. If the executive has an active batch with room below n the lead joins it; if the batch is full the lead is added as a follow-up task due now |
| Requeued lead | Keeps its original creation time, so it does not go to the back |
| No eligible executive | Leads wait; the queue screen shows "No executive available" |

---

## 4. Batch, feedback and follow-up

### 4.1 Batch

- One active batch per executive, enforced by the database.
- A batch completes when every item is complete; `completed_at` is set and the executive's `idle_since` is set.
- A lead has at most one incomplete batch item.

### 4.2 Feedback

Fields: call status, customer interest, customer concern, sales note, next action, and the inputs the next action needs.

**Call status** — what happened on the call (proposed list, Q-13):

| Value | Counts as connected |
| :--- | :-- |
| Connected | Yes |
| Connected (WhatsApp) | Yes |
| Not Connected | No |
| No Answer | No |
| Busy | No |
| Switched Off | No |
| Call Later | Yes |
| Wrong Number | No |

"Under Age" and "Over Age" from v-03 are outcomes, not call results; they become reasons under next action Invalid. "No Answer" and "Switched Off" are added to the v-03 list because they are the second and fifth most used values in the interim data.

**Customer interest** — Highly Interested, Interested, Maybe, Decision Pending, Not Interested. Required when the call connected; otherwise recorded as "Not applicable".

**Next action** and what each requires:

| Next action | Allowed when | Required input | Batch item | Lead status |
| :--- | :--- | :--- | :--- | :--- |
| Follow-up | Always | Date and time in the future, within the maximum days ahead | Completed | Follow-up |
| Converted | Call connected | The enrollment form, submitted | **Stays open** until Accounts verifies the first payment (decided, Q-03) | Enrolled – Pending Payment |
| Not Interested | Call connected | Sales note | Completed | Not Interested |
| Unreachable | Call not connected, and the lead has at least the minimum number of attempts | — | Completed | Unreachable |
| Lost | Always | Reason | Completed | Lost |
| Invalid | Always | Reason: wrong number, under age, over age, junk | Completed | Invalid |

Consequences of these rules:

- A call that did not connect, below the attempt minimum, must end in a Follow-up (a retry time). The executive cannot close a lead after one unanswered call.
- A connected call always ends in a decision or a scheduled next contact. The interim system added exactly this guard after open leads with no plan piled up in everyone's task list.
- A converted lead is the one outcome that does not free its slot at once. Its batch item completes when the first payment is verified, or as "Enrollment cancelled" if the enrollment is cancelled. A rejected payment leaves it open.
- Every feedback row is written once and never changed. A correction is a new note that refers to the original.

Each feedback also updates the lead: `last_action_at`, `interest_level`, `first_contacted_at` and `first_contacted_by` on the first connected call, attempt count.

### 4.3 Follow-ups

- A lead has at most one open follow-up. Scheduling a new one closes the previous as superseded.
- Recording feedback on a lead closes its open follow-up and links the feedback to it.
- Follow-up leads are outside the batch and do not block a new batch, unless they make the executive Lazy.
- Follow-up kinds: Sales, Installment, Transferred-in, Payment-rejected. All appear in the same due list with a label.

**My Tasks sections**

| Section | Content | Order |
| :--- | :--- | :--- |
| Current batch | Incomplete items of the active batch | Assignment order |
| Overdue | Open follow-ups whose time has passed by more than 15 minutes | Oldest first |
| Follow-up due | Open follow-ups due today up to now | Priority, then time |
| Upcoming | Open follow-ups later today and in the future | Time |

---

## 5. Permission matrix

**A** all records · **T** records of own team or assigned teams · **O** own records only · **—** no access.

| Capability | Admin | Supervisor | Team Leader | Sales Exec | Marketing | Accounts |
| :--- | :-- | :-- | :-- | :-- | :-- | :-- |
| Manage employees, roles, status | A | — | — | — | — | — |
| Reset a password | A | — | — | — | — | — |
| Approve profile change requests (not role or status) | A | T | T | — | — | — |
| Manage teams and supervisor assignment | A | — | — | — | — | — |
| Manage shifts | A | — | — | — | — | — |
| Per-date duty override, pause and resume, clear Lazy | A | T | T | — | — | — |
| Course catalogue and batches | A | — | — | — | — | — |
| Campaigns | A | — | — | — | A | — |
| Upload leads, single entry | A | — | — | — | A | — |
| Add a walk-in lead | A | T | T | Q-12 | A | — |
| Duplicate review | A | T | — | — | — | — |
| Central queue: view | A | A | A | — | A | — |
| Central queue: assign manually | A | T | T | — | — | — |
| Lead list, search, export | A | T | T | — | A (no call notes) | — |
| Open a lead and its timeline | A | T | T | O | — | Enrollment data only |
| Record feedback, schedule follow-up | — | — | — | O | — | — |
| Past inquiries on the same number | A | T | T | O, from an owned lead | — | — |
| Reassign, requeue, reopen | A | T | T | — | — | — |
| Request a transfer | — | — | — | O | — | — |
| Approve or reject a transfer | A | T | T | — | — | — |
| Create an enrollment | — | — | — | O | — | — |
| Approve a discount above the limit | A | T | T | — | — | — |
| Cancel an enrollment | A | T | T | — | — | A |
| Verify or reject payments, issue receipts | A | — | — | — | — | A |
| Dashboards and reports | A | T | T | O | Campaign reports | Finance reports |
| System settings, engine switch | A | — | — | — | — | — |
| Audit log | A | — | — | — | — | — |
| View a staff member's password on the decrypt screen (decided, Q-01) | A | — | — | — | — | — |

Rules behind the table:

1. The table is implemented once, as policies; no controller decides access on its own.
2. "T" for a supervisor means the teams in `supervisor_teams`; for a team leader, the team they lead.
3. Admin, Supervisor and Team Leader do not record sales feedback on leads; they manage. If team leaders should also sell, that is Q-10.
4. No role can delete history.

---

## 6. Phone numbers

One function, used by every entry path, the login form, the duplicate check and the migration.

```
normalise(input):
    keep digits; remember whether the input started with "+" or "00"
    if digits start with "880" and the rest is a valid local number  → local form "0" + rest
    if 10 digits starting with "1"                                   → put the "0" back in front
    if 11 digits matching 01[3-9]########                            → Bangladeshi mobile, stored as is
    else if 8–15 digits and the input was marked international       → stored as "+" + digits
    else                                                             → invalid
```

| Input | Stored |
| :--- | :--- |
| `01711-223344` | `01711223344` |
| `+880 1711 223344` | `01711223344` |
| `8801711223344` | `01711223344` |
| `1711223344` (a spreadsheet dropped the zero) | `01711223344` |
| `p:+8801711223344` (Meta export) | `01711223344` |
| `+971 50 123 4567` | `+971501234567` |
| `12345` | invalid |

All of a lead's numbers are held in one lookup table (`lead_phones`), so a duplicate check is one indexed query across primary, alternative and WhatsApp numbers.

---

## 7. Duplicate decisions

Input: name N, numbers P, course C (the campaign's course if the row names none), campaign K.
Name comparison ignores case, extra spaces and punctuation. A name written once in Bangla and once in English will not match and goes to review.

| # | Finding | v-03 says | Decided rule (Q-02) |
| :-- | :--- | :--- | :--- |
| D0 | No stored number matches | New lead | New lead |
| D1 | Same number, name, course and campaign | Suppress | Suppress; log the inquiry; apply D7 |
| D2 | Same number, name and course; different campaign | Review | Treat as D1 — the same person clicked another advert |
| D3 | Same number, different name | Review | Review: "different student on the same number" |
| D4 | Same number and name, different course | Review | Review: "additional course interest" |
| D5 | Incoming primary equals a stored alternative or WhatsApp number, or the reverse | Review | Review: "cross-number match" |
| D6 | More than one stored lead matches | — | Review, listing all matches |
| D7 | The matched lead of D1/D2 is closed | — | Reopen it to its previous owner if still active, otherwise to the queue |
| D8 | The matched lead of D1/D2 is open and was last touched on an earlier day | — | Create a follow-up due now for its owner: "customer inquired again" |

Why D2, D7 and D8 matter: 491 of the 2,260 inquiries in the interim system are repeats. Under v-03 as written, each would need an admin decision. The automatic rules were confirmed on 16 November 2026.

**Review decisions**

| Decision | Effect |
| :--- | :--- |
| Approve as separate lead | A new lead is created. **If the matched lead has an active owner, the new lead goes to the same owner** as a follow-up task, so one family is never called by two executives (BR-10). Otherwise it enters the queue |
| Merge | Blank fields of the matched lead are filled; new numbers are added; the inquiry is logged on it |
| Discard | Nothing is created; the inquiry is logged as discarded |
| Edit and dispatch | The reviewer corrects the data, then it is processed again from D0 |

---

## 8. Notifications

| Event | Sales Exec | Team Leader | Supervisor | Admin | Accounts |
| :--- | :-- | :-- | :-- | :-- | :-- |
| New batch assigned | Own | | | | |
| Follow-up due | Own | | | | |
| Follow-up overdue | Own | Team | | | |
| Lead unworked past the limit | Own | Team | | | |
| Executive became Lazy | Own | Team | Teams | | |
| Lead reassigned or requeued | Old and new owner | Team | | | |
| Transfer requested | | Approvers | | | |
| Transfer approved, rejected or auto-approved | Requester, receiver | Both TLs | | | |
| Duplicate review waiting | | | Teams | Yes | |
| Queue has leads and no eligible executive | | | | Yes | |
| Payment submitted | | | | | Yes |
| Payment verified | Own | | | | |
| Payment rejected | Own | Team | | | |
| Installment due in the configured days | Own | | | | |
| Installment overdue | Own | Team | | | Yes |
| Discount approval needed | | Team | | | |
| Course has no free seat | | | | Yes | |

Delivered in the application first. SMS, WhatsApp and email come later and only for customer messages (Q-16).

---

## 9. Overdue, Unworked and Lazy

| Term | Definition |
| :--- | :--- |
| Due | The follow-up time has arrived |
| Overdue | The follow-up is still open after its time |
| Unworked | A batch lead has no feedback after the first-action limit, counted in the executive's working time |
| Lazy | Either Lazy condition below holds |

**The Lazy rule** — the three specification versions defined it differently; the decision of 16 November 2026 (Q-04) combines them. An executive is Lazy when **either** condition holds:

| Condition | Definition | Origin |
| :--- | :--- | :--- |
| (a) Overdue follow-up | A follow-up is still open at the end of the executive's next working shift after the shift in which it fell due | v-03, measured in working time so that days off do not count |
| (b) Unfinished batch | The current batch still contains a lead with no feedback two working days after the batch was issued | v-01, v-02 |

**Interaction with the batch-slot decision (Q-04a).** A converted lead keeps its batch open until Accounts verifies payment. Read literally, condition (b) would then mark an executive Lazy because a customer was slow to pay. The rule above therefore counts only leads the executive has **not yet acted on**. Leads in "Enrolled – Pending Payment" or "Payment Under Review" hold the slot but never cause Lazy. This reading awaits confirmation.

Evaluation runs every ten minutes:

```
for each active sales executive:
    a = an open follow-up exists past the end of the next working shift after its due shift
    b = the active batch has an item with no feedback, and two working days have passed since it was issued
    if (a or b) and lazy_since is null and not in grace:  set lazy_since = now; record which condition; notify
    if not a and not b and lazy_since is set:             clear lazy_since; trigger the engine
```

Recovery: the executive records the missing feedback — on the overdue follow-ups for (a), on the untouched batch leads for (b). Manual clearing by a TL, supervisor or admin needs a reason and sets a grace period to the end of the current shift. Without a grace period the next evaluation would flag the executive again at once.

"Working day" means a day on which the executive's effective shift runs: weekly days off, leave and off-duty overrides are skipped.

---

## 10. Transfers

### 10.1 States

```
Pending ──(all required approvals)──▶ Approved ──▶ executed
   │──(an approver rejects)────────▶ Rejected
   │──(requester withdraws)────────▶ Cancelled
   │──(N days pass, not rejected)──▶ Approved (auto) ──▶ executed
   └──(lead or target no longer valid)─▶ Cancelled (system)
```

"Cancelled" must be added to the status list.

### 10.2 Who approves

| Case | Required approval |
| :--- | :--- |
| Sender and receiver in the same team | That team's leader |
| Different teams | Sender's TL and receiver's TL |
| One person leads both sides | One approval covers both |
| A team has no active leader | A supervisor of that team, or an admin |
| Any case | A supervisor of the team, or an admin, may approve in place of a TL |
| Requested by an admin | No approval; executes at once; `is_admin_override` set |

### 10.3 Rules

1. A reason is mandatory. One pending transfer per lead.
2. While pending, nothing about the lead changes; the owner keeps working it.
3. The receiver must be an active Sales Executive and not the sender.
4. A rejection needs a reason.
5. Auto-approval: a daily job approves requests pending for `transfer_auto_approval_days` and marks them `is_auto_transferred`.
6. Before executing, the system checks again: the lead still belongs to the sender and is open; the receiver is still active. If not, the transfer is cancelled with the reason.

### 10.4 Execution

One transaction: previous owner = sender; owner = receiver; team = receiver's team; the sender's incomplete batch item for the lead is completed as "Transferred"; open follow-ups move to the receiver with their times unchanged; if there was none, a follow-up due now is created so the lead appears in the receiver's list; `transferred_at` is set; the timeline records it; both sides are notified.

The lead does not enter the receiver's batch and does not wait for the receiver to be available.

**Risk to accept knowingly:** auto-approval after seven days means a transfer can complete without the receiving team ever agreeing. The rule is in v-03 and is implemented as written; the transfer inbox shows the countdown so it is never a surprise.

---

## 11. Enrollment, installments and payments

### 11.1 Amounts

```
discount_amount = round(base_fee × discount_percentage / 100, 2)
final_amount    = base_fee − discount_amount
```

- The executive enters the discount; the system computes the final amount. The final amount is never typed.
- A discount above the configured limit puts the enrollment in "awaiting discount approval" (Q-15).
- Installments: count between 1 and the configured maximum; each amount is positive; dates are strictly increasing; the first is due today; **the amounts total exactly the final amount** — the last installment absorbs rounding.
- `base_fee` is copied from the course at the moment of enrollment and does not change if the course price changes later.

### 11.2 States

| Record | States |
| :--- | :--- |
| Enrollment | Pending Payment → Payment Under Review → Enrolled; Cancelled. `fully_paid_at` is set when the last installment is verified |
| Installment | Pending → Under Review → Paid; Overdue when the due date passes unpaid |
| Payment | Pending → Verified, or Rejected |

### 11.3 Effects

| Event | Payment | Installment | Enrollment | Lead | Other |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Executive saves the enrollment | — | All Pending | Pending Payment | Enrolled – Pending Payment | Seat reserved; link created; **batch item stays open** |
| Customer submits a payment | Pending | Under Review | Payment Under Review (first payment) | Payment Under Review (first payment) | Accounts notified; batch item still open |
| Accounts verifies the first payment | Verified | Paid | Enrolled | Converted | **Batch item completed**; if it was the last, the batch completes and the engine runs; receipt issued; executive notified |
| Accounts verifies a later installment | Verified | Paid | Enrolled | Converted | Receipt issued; executive notified |
| Accounts rejects | Rejected, with reason | Pending | Pending Payment, if nothing is verified yet | Enrolled – Pending Payment, if nothing is verified yet | Follow-up due now for the executive; batch item still open |
| Last installment verified | Verified | Paid | `fully_paid_at` set | Converted | — |
| Enrollment cancelled | — | — | Cancelled | Follow-up, with a task for the owner | Seat released; batch item completed as "Enrollment cancelled" |

Because the slot is held (decided, Q-03), the time Accounts takes to verify limits how many leads an executive can receive. The Accounts queue should show how long each payment has waited and alert past a target time (Q-15 item 11).

### 11.4 Seat allocation

Already implemented and tested in `CreateEnrollmentFromLead`: lock the course, pick the lowest-numbered batch whose non-cancelled enrollments are below capacity, refuse if none. Two points to settle (Q-15): whether a batch that has already started should still receive students, and how long an unpaid enrollment may hold a seat.

### 11.5 Manual payment

Required: channel (bKash personal, bKash merchant, Nagad, Rocket, bank), the sending number or account, the amount, and a transaction ID or a slip image or both. A transaction ID that already exists for the same channel is refused.

### 11.6 Gateway payment

The gateway's confirmation is checked server to server; the browser's return is never trusted. A confirmed payment is recorded with the gateway's reference. v-03 routes it to "Payment Under Review" like a manual one; whether Accounts must still press verify on a gateway-confirmed payment is Q-15.

### 11.7 Receipt

Number format `MR-<year>-<six digits>`, issued in sequence without gaps under a lock, one per verified payment, never reused. Contents: student, course, batch, amount, method, reference, date, verifier, remaining balance.

### 11.8 Customer page

- The link token is random and long enough not to be guessed; it is the only credential, so it is treated as a secret and never logged.
- The page shows one enrollment only, with the phone number partly masked.
- It stops working when the enrollment is cancelled, and after a configured period once fully paid.
- Requests are rate limited; search engines are told not to index it.
- Slips: JPEG, PNG, WebP or PDF up to 5 MB, stored on a private disk and shown to Accounts through an authorised route.

### 11.9 Installment reminders

Daily: every Pending installment whose due date is within `installment_followup_lead_days`, and whose `followup_generated` is false, gets an Installment follow-up for the enrollment's executive — or for the lead's current owner if that executive is no longer active — and the flag is set. Every Pending installment past its due date becomes Overdue.

---

## 12. Deactivation and resignation

When an employee's status leaves Active:

1. Their sessions and remembered logins end.
2. Their active batch is closed. Leads with no feedback return to the central queue with their original age.
3. Leads in Follow-up, and enrollments awaiting payment, go to a reassignment list for their team leader, each with its history. They are not redistributed automatically (Q-11): someone should choose who continues a conversation.
4. Their open follow-ups stay attached to the leads and move with them.
5. Their pending transfers, in either direction, are cancelled.
6. Converted leads keep the employee as the converting owner for reports.
7. Every step is written to each lead's timeline.

A resigned employee is never deleted; their history must remain.

---

## 13. Reopen and reactivation

| Case | Rule |
| :--- | :--- |
| Admin or TL reopens a closed lead | Reason required; who, when and why are recorded; the lead returns to its last owner if active, otherwise to the queue; status becomes Follow-up with a task due now |
| A new inquiry arrives on a closed lead | Rule D7 in §7 |
| A Converted lead | Only an admin can reopen it, and only after its enrollment is cancelled |

---

## 14. Scheduled jobs

| Job | Frequency | What it does | Safe to repeat because |
| :--- | :--- | :--- | :--- |
| Distribution tick | Every minute | §3.3 | Takes a lock; assigns only unowned leads |
| Shift close-out | Every 5 minutes | Clears check-in for executives past shift end | Sets a value that is already set |
| Follow-up reminders | Every 5 minutes | Notifies owners of follow-ups that just fell due | A "notified" flag |
| Overdue and Lazy check | Every 10 minutes | §9 | Compares current state only |
| Unworked check | Every 10 minutes | Flags batch leads past the first-action limit | A flag per item |
| Transfer auto-approval | Hourly | §10.3 rule 5 | Acts on Pending only |
| Installment reminders | Daily 08:00 | §11.9 | `followup_generated` flag |
| Installment overdue | Daily 00:10 | Marks unpaid past-due installments | Acts on Pending only |
| Unpaid enrollment check | Daily 09:00 | Alerts on enrollments unpaid beyond the limit | Compares current state |

All need the scheduler cron entry and a queue worker on the server. Neither is set up today; `routes/console.php` defines no task.

---

## 15. Settings

The two existing keys keep their names because they are seeded and tested; v-03's names are shown for reference.

| Key | Type | Default | Range | v-03 name | Exists |
| :--- | :--- | :--- | :--- | :--- | :-- |
| `lead_distribution_engine_active` | boolean | true | — | same | No |
| `lead_batch_size` | integer | 3 | 1–50 | `default_batch_size_n` | Yes |
| `assignment_cutoff_minutes` | integer | 30 | 0–180 | `shift_cutoff_minutes` | Yes |
| `lazy_followup_overdue_shifts` | integer | 1 (next working shift) | 1–5 | `overdue_followup_lazy_threshold_t` | No |
| `lazy_batch_unfinished_days` | integer | 2 working days | 1–10 | — (v-01 §10) | No |
| `transfer_auto_approval_days` | integer | 7 | 1–30 | same | No |
| `installment_followup_lead_days` | integer | 2 | 0–14 | same | No |
| `first_action_limit_minutes` | integer | 60 | 5–600 | — (v-01 §21) | No |
| `unreachable_min_attempts` | integer | 3 | 1–10 | — | No |
| `followup_max_days_ahead` | integer | 30 | 1–180 | — | No |
| `max_discount_without_approval` | percent | to be set | 0–100 | — | No |
| `max_installments` | integer | to be set | 1–24 | — | No |
| `unpaid_enrollment_alert_days` | integer | 7 | 1–60 | — | No |

Settings are read through one typed accessor with caching. A changed value takes effect on the next engine run or job run. Every change is written to the audit log.

---

## 16. Staff password storage

Decided on 16 November 2026 (Q-01): passwords are stored with the reversible transformation of `Pass encrypt and decrypt formula.pdf`, and Admin has a decrypt screen. This section fixes the details the PDF leaves open, so every implementation produces the same stored value.

### 16.1 Procedure

Inputs: the password as UTF-8 bytes `P[0..n-1]`; the key as UTF-8 bytes `S`; `K[i] = S[i mod length(S)]`. All arithmetic is modulo 256.

```
store(password):
    R[i] = P[n-1-i]                                   reverse
    A[0] = R[0]
    A[i] = (R[i] + R[i-1] + i) mod 256     for i ≥ 1
    C[i] = (A[i] + K[i]) mod 256
    return lowercase hexadecimal of C                 two characters per byte

reveal(stored):
    C = bytes from hexadecimal
    A[i] = (C[i] − K[i]) mod 256
    R[0] = A[0]
    R[i] = (A[i] − R[i-1] − i) mod 256     for i ≥ 1
    P[i] = R[n-1-i]
    return P as UTF-8 text

check(entered, stored):
    return constant-time-equal( store(entered), stored )
```

### 16.2 Test vector

With the illustrative key `KEY` (not the real one):

| Step | Values |
| :--- | :--- |
| Password `abc` as bytes | 97, 98, 99 |
| Reversed R | 99, 98, 97 |
| A | 99, (98+99+1) = 198, (97+98+2) = 197 |
| Key bytes K | 75, 69, 89 |
| C | 174, (198+69) mod 256 = 11, (197+89) mod 256 = 30 |
| Stored | `ae0b1e` |

Revealing `ae0b1e` with the same key must return `abc`. This pair belongs in the automated tests, together with an empty password, a 12-character password, a password longer than the key, and a Bangla password.

### 16.3 Details the PDF does not specify

| Point | Rule |
| :--- | :--- |
| Encoding of text | UTF-8, for both password and key |
| Encoding of the result | Lowercase hexadecimal. A 12-character Latin password stores as 24 characters; the column allows 255 |
| Where the key lives | An environment value on the server (for example `PASSWORD_CIPHER_KEY`), read through configuration. Not in code, not in any document, not in the repository |
| Which key | **A new one.** The key printed in the PDF is in the Git history and in the web-served folder (W-SEC-01) and must be treated as known |
| Changing the key later | Reveal every stored value with the old key and store it again with the new one, in one maintenance run |
| Existing accounts | Their one-way hashes cannot be converted. Every account receives a temporary password at switch-over and the forced-change flow applies |

### 16.4 Fitting it into the application

- Implement the procedure as one class with `store`, `reveal` and `check`, and register it as the application's password hasher, so that sign-in, "current password" validation and the model's password handling keep working through the framework's normal path. Confirm this with the existing login and password-change tests.
- `reveal` is called from exactly one place: the admin decrypt screen.
- The test "stores user passwords as one-way hashes" (`tests/Feature/Models/EmployeeTest.php:20`) is replaced by tests of the procedure and of the screen's access rules.

### 16.5 The decrypt screen

| Rule | Detail |
| :--- | :--- |
| Who | Admin only |
| Confirmation | The admin re-enters their own password before a value is shown |
| Reason | A reason is required |
| Record | Admin, employee, time, reason and IP address are written to the audit log; the password itself is never logged |
| Display | Shown once on screen, not sent by email or notification, not included in any export |
| Notice | Staff are told that administrators can read their passwords |

### 16.6 Exposure that remains

Recorded so it is a known, accepted property of the design rather than a surprise (W-SEC-07):

- Anyone who holds a copy of the database and the key can read every password.
- The key can be worked out from any one stored value whose password is known, for as many positions as that password is long. Nobody outside the database administrators should ever see a stored value.
- Two staff with the same password have the same stored value.
- Actions taken by an admin using a staff member's password are recorded as that staff member's actions. The audit log of the decrypt screen is the only link back to the admin.

Database access, backups and the server environment file therefore need the same protection as the passwords themselves.


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

### Follow-up stages

```
phase(f): if done: Done
          elif f.time <= now: Overdue
          elif f.time <= now + N hours: Coming up
          else Scheduled
every minute:
  Coming up and not yet reminded and created over 5 minutes ago -> remind owner once
  Overdue and not announced -> announce; announced and last notice older than R hours -> repeat
  Overdue older than the Lazy threshold -> tell owner and managers once a day
  Done -> clear its notices
```

### Leave

```
days = working days in range (Friday not counted; maternity counts every day)
refuse if days > balance of the type, or sick > C days without a certificate confirmation
required = 0 for admin (approved at once)
         = 2 for a sales executive whose team has a leader, else 1
step 1 (of 2): the team leader of the applicant's team -> approvals_done = 1
final: supervisor of the team or admin; an admin may settle any step; a rejection ends it
```

### Conversion

```
enroll(lead): owner, lead open, course active, discount <= cap, 1..max installments
              reserve first batch with room; amount = fee x (1 - discount); split into installments
              (rounding on the first); lead -> Enrolled - Pending Payment; open follow-ups close
submit(installment): amount == installment; payment Pending; installment Under Review; lead Payment Under Review
verify: installment Paid, enrollment Enrolled, lead Converted, slot freed
reject: back to Pending with the reason
```

### Repeat inquiry

```
log inquiry
closed (not interested, unreachable, lost, invalid): reopen; previous owner active -> assign (outside the cap)
                                                     else status New in the queue
open: owner exists and last contact not today -> follow-up now; else log only
enrolled or paying: log only
```

### Assignment with limits

Available for the engine = Available and Auto mode and remaining daily limit not zero. Each allocation is clamped to the remaining limit. Only engine runs add to the count; hand assignment, reassignment and returning customers do not.
