# 09 — Decisions and Open Questions


<!-- build-update -->

## Build update — 17 November 2026

| Question | State |
| :--- | :--- |
| Q-01 Passwords | Implemented as decided |
| Q-02 Repeat inquiries | Not implemented; duplicates still go to manual review |
| Q-03 Batch slot of a converted lead | Implemented: released when Accounts verifies |
| Q-04 Lazy rule | Half implemented: overdue follow-up only |
| Q-04a | Still open, but harmless until the second Lazy condition is built |

New questions raised by the build:

| # | Question | Default in the code |
| :-- | :--- | :--- |
| Q-21 | Are the priority windows right for Pedago: hot 60 minutes, warm 24 hours, cold after 72 hours? | Those values; all three are settings |
| Q-22 | Should the hottest leads go to the best-converting executives instead of fair round-robin? | Fair round-robin |
| Q-23 | Should a team leader be allowed to reassign follow-ups inside their own team? | No — admin only |
| Q-24 | May Accounts staff see which executive sold an enrollment? | Yes, shown on the payment row |
| Q-25 | Should supervisors get the reports page for their teams? | No — admin only |

<!-- build-update -->

Decisions that the documents could not settle. Four were answered on 16 November 2026 and are recorded in Part 1. Sixteen remain open in Part 2; each has a recommended default, and the SRS and logic documents are written with those defaults so work can proceed.

| # | Question | Status | Decision or recommended default |
| :-- | :--- | :-- | :--- |
| Q-01 | How are staff passwords stored, and how does an admin enter a staff account? | **Decided** | Reversible, using the PDF formula, with an admin-only decrypt screen |
| Q-02 | Are repeat inquiries handled automatically, with an inquiry log? | **Decided** | Yes — automatic rules plus an inquiry log |
| Q-03 | When does a converted lead free its batch slot? | **Decided** | When Accounts verifies the payment (v-03) |
| Q-04 | What exactly makes an executive Lazy? | **Decided** | Either condition: an overdue follow-up, or an unfinished batch |
| Q-04a | Do leads waiting for payment count as an "unfinished batch" for Lazy? | Open — follows from Q-03 and Q-04 | No |
| Q-05 | Is the lead queue shared by all teams? | Open | Yes |
| Q-06 | Must an executive check in to receive leads? | Open | Yes |
| Q-07 | In what order is the queue served, and are part-filled batches topped up? | Open | Campaign priority, then oldest; no top-up |
| Q-08 | Who approves which transfers? | Open | See below |
| Q-09 | Role details: "Manager", Marketing Executive, supervisors per team | Open | See below |
| Q-10 | Can team leaders or supervisors own and call leads? | Open | No |
| Q-11 | What happens to a departed executive's worked leads? | Open | Team leader reassigns them |
| Q-12 | May executives add their own referral leads and check a number? | Open | Yes to both, limited |
| Q-13 | Status and call-status lists; attempts before "Unreachable" | Open | See below |
| Q-14 | Are the v-01 items missing from v-03 still wanted? | Open | Yes |
| Q-15 | Finance rules | Open | See below |
| Q-16 | Who sends the payment link, and through what? | Open | Executive copies it; provider later |
| Q-17 | Code structure and permitted packages | Open | Standard Laravel folders; a short package list |
| Q-18 | Interface language | Open | Bangla and English |
| Q-19 | Migration scope and go-live | Open | Migrate everything |
| Q-20 | Hosting and operations | Open | See below |

---

# Part 1 — Decisions recorded on 16 November 2026

## Q-01 — Passwords: reversible, by the PDF formula

**Decision.** Staff passwords are stored using the transformation in `Pass encrypt and decrypt formula.pdf`. The database holds the transformed value. A decrypt module is available to Admin only, so an admin can read a staff member's password and open their account. This is the policy stated in `prompt.txt`.

**What this changes.** The code hashes passwords one way today, so the change is still to be built: document 04 §16 gives the exact procedure and a test vector; document 05 §2.1 gives the data change. Because a one-way hash cannot be converted, **every existing account must be given a new temporary password when the change goes live.**

**Points to settle while building it** — none of them changes the formula:

| # | Point | Recommendation |
| :-- | :--- | :--- |
| 1 | The key printed in the PDF is in the Git repository and in the web-served folder | Choose a new key. Keep it in the server's environment configuration, never in code, a document or the repository |
| 2 | The formula produces raw bytes | Store as hexadecimal text |
| 3 | Who sees the decrypt screen | Admin only; the admin re-enters their own password and gives a reason; each use is written to the audit log |
| 4 | Admins' own passwords | Readable by other admins under this design — confirm that is intended |
| 5 | Staff awareness | Tell staff that an admin can read their password, so nobody reuses a personal one |

The remaining exposure after these measures is stated in W-SEC-07: anyone who obtains the database together with the key — or together with any one known password — can read all passwords. Database and backup access therefore needs the same care as the key.

## Q-02 — Repeat inquiries: automatic rules with an inquiry log

**Decision.** Every submission is logged as an inquiry. A repeat from the same person for the same course creates no new lead: a closed lead is reopened to its previous owner, and an open lead is resurfaced to its owner. Only a different student, a different course or a cross-number match goes to manual review.

**Where it is specified.** Rules D0–D8 in document 04 §7; tables `lead_inquiries` and `lead_phones` in document 05 §3. This matches what the Google Sheet does today.

## Q-03 — Batch slot: held until Accounts verifies the payment

**Decision.** As v-03 §9.1: a converted lead keeps its place in the executive's batch through "Enrolled – Pending Payment" and "Payment Under Review". The batch item completes when Accounts verifies the first payment.

**Consequences now written into the rules** (document 04 §4.2 and §11.3):

- An executive with one lead awaiting payment cannot receive a new batch until it is verified, because a batch must be complete before the next is issued.
- If Accounts rejects the payment, the slot stays occupied.
- If the enrollment is cancelled, the batch item completes as "Enrollment cancelled" and the lead returns to Follow-up.
- The speed of Accounts now directly limits how many leads the sales team can take. A target time for verification, and an alert when a payment has waited too long, are worth adding to the Accounts dashboard.

This is recorded as accepted risk R-1 in document 06.

## Q-04 — Lazy: either condition

**Decision.** An executive is Lazy when **either** holds:

- **(a)** a follow-up is still open at the end of their next working shift after the shift in which it fell due; or
- **(b)** their current batch has been unfinished for two working days.

Lazy clears when neither holds, or when a team leader, supervisor or admin clears it with a reason.

**Where it is specified.** Document 04 §9.

## Q-04a — Follow-on question from Q-03 and Q-04 together

Under Q-03 a lead awaiting payment keeps its batch unfinished. Under Q-04(b) an unfinished batch for two working days makes the executive Lazy. Taken together literally, **an executive would be marked Lazy because a customer took three days to pay or because Accounts had not yet verified** — for doing their job well.

**Recommended and written into document 04 §9:** for condition (b), a batch counts as unfinished only while it contains a lead the executive has not yet acted on. Leads in "Enrolled – Pending Payment" or "Payment Under Review" do not count towards Lazy. They still occupy the slot, as decided in Q-03.

Please confirm, or say if the literal reading is intended.

---

# Part 2 — Open questions

## Q-05 — One queue or one per team

No document says. **Default:** one queue for the company; any eligible executive in an active team can receive any lead; a lead's team is its owner's team. Alternatives: a campaign assigned to specific teams; or each team with its own queue. The first alternative is a small later addition if needed.

## Q-06 — Presence

v-03 uses the roster alone. The interim system requires being signed in, but its data shows everyone permanently "online". **Default:** a "start my day" check-in, cleared automatically at shift end. Alternative: roster only, accepting that an absent executive receives a batch that then waits for a team leader to notice.

## Q-07 — Queue order and top-up

1. **Order.** Default: campaign priority (High, Normal, Low), then oldest first. Without priority, the roughly 1,200 historical unassigned leads would be served before any fresh inquiry.
2. **Top-up.** When an executive holds a part-filled batch (possible under scarcity) and new leads arrive, do they get more? v-03 says no — finish first. The interim system says yes — fill to 3. Default: no top-up, as v-03.

## Q-08 — Transfer approvals

v-03 defines only cross-team transfers approved by both team leaders. **Defaults:**

- Same team: that team's leader.
- A supervisor of the team, or an admin, may approve in a leader's place — and must, when a team has no leader.
- One person leading both sides approves once.
- Auto-approval after the configured days applies as v-03 says. *Alternative worth considering:* auto-approve only the sender's side, so a receiving team is never given a lead without agreeing.

## Q-09 — Roles

1. The interim "Manager (Sales + Marketing Manager)" has no ERP equivalent. Default: Supervisor for sales oversight; Marketing Executive permissions granted separately if the same person does both.
2. Marketing Executive is in the code but not in v-03's role table. Default: it is an official sixth role.
3. Can a team have more than one supervisor? The documents say "1:N" but the table and the form allow several. Default: several are allowed.
4. Accounts currently has no screen at all. Confirmed as intended until Phase 3?

## Q-10 — Do team leaders and supervisors sell?

The assign dropdown lists them as possible lead owners. **Default:** no — only Sales Executives own leads and receive batches. If team leaders also call leads, they need batches, tasks and the Lazy rule too, and someone must approve their transfers.

## Q-11 — Leads of a departed executive

v-01: all go to a reassignment queue. v-03: unworked leads go to the central queue. **Default:** untouched leads return to the central queue; leads already in conversation (Follow-up, awaiting payment) go to the team leader's reassignment list with their history, because the next person should be chosen, not random.

## Q-12 — What executives may do beyond their batch

1. **Add a referral or walk-in lead of their own**, assigned to themselves. The interim system allows it; v-03 gives lead entry to Admin and Team Leader only. Default: allow, through the same duplicate rules, outside the batch count.
2. **Check a number** before adding it — seeing only whether it exists, its status and its owner's name. v-03 forbids typed search to prevent poaching. Default: allow this minimal check only inside the "add referral lead" form, with every check logged.

## Q-13 — Status lists

1. Make **Contacted** and **Interested** facts on the lead rather than statuses, since no next action leads to them (document 04 §1.1). Default: yes.
2. Add status **Invalid** for wrong numbers, age outside the range, and junk. Default: yes.
3. Call results: add **No Answer** and **Switched Off**; treat **Under Age** and **Over Age** as reasons for Invalid. Default: yes.
4. Minimum attempts before **Unreachable** can be chosen. Default: 3, as a setting.
5. What are the valid age limits, per course or overall? Needed to make "under age" a rule rather than a judgement.

## Q-14 — v-01 items not in v-03

Still required? Default: yes, at second priority.

- Flag a lead with no first action within a set time.
- Reopen a closed lead with a reason.
- The eight executive KPIs and the ten dashboard figures.
- The notification matrix.
- The eleven search filters for Admin and Team Leader.
- Leave and absence handling.
- Automatic reassignment as a later phase.

## Q-15 — Finance rules

| # | Question | Default |
| :-- | :--- | :--- |
| 1 | Largest discount an executive may give alone; who approves more | A setting; team leader approves |
| 2 | Largest number of installments; smallest first payment | Settings; to be given |
| 3 | How long may an unpaid enrollment hold a seat — and, after Q-03, a batch slot | Alert at 7 days; cancel by a team leader |
| 4 | May a batch that has already started receive new students | Yes, until an admin closes it to admissions |
| 5 | Should the executive choose the batch instead of the system | No — lowest-numbered with space, as built |
| 6 | Must Accounts verify a gateway-confirmed payment | Verified automatically; Accounts reconciles. After Q-03 this also frees the batch slot at once for online payments |
| 7 | Is AamarPay needed (v-02 lists it, v-03 does not) | No |
| 8 | Refunds, cancellation after payment, change of batch or course | Out of scope for now |
| 9 | Receipt layout, numbering and legal text | Number `MR-<year>-<sequence>`; layout to be supplied |
| 10 | Are part payments of one installment accepted | No |
| 11 | Target time for Accounts to verify a payment | To be given; shown as an alert on the Accounts dashboard |

## Q-16 — Sending the link and messages

v-02: the salesperson sends the link by WhatsApp, SMS or email. v-03: "link sent via SMS / WhatsApp / Email" by the system. **Default:** in Phase 3 the executive copies the link or opens WhatsApp with it prefilled; automatic SMS or WhatsApp sending comes in Phase 4 once a provider is chosen. Which provider do you use or prefer?

## Q-17 — Structure and packages

1. v-03 prescribes `app/Modules/Lead/…`; `AGENTS.md` forbids new base folders without approval; the code uses `app/Actions`. **Default:** standard Laravel folders — `app/Services`, `app/Policies`, `app/Enums`, `app/Http/Requests`, `app/Jobs`, `app/Notifications` — and no `app/Modules`.
2. Packages need approval under `AGENTS.md`. Likely needed: an Excel reader and writer (upload and export), a PDF generator (receipts), the gateway SDKs or plain HTTP, an SMS client. Approve the list once?
3. v-02 mentions Breeze, Sanctum and a JSON API. Nothing needs an API today. **Default:** server-rendered pages only; add an API if a mobile app is ever planned.

## Q-18 — Language

The interim interface is Bangla; the ERP is English. **Default:** both, switchable per user, built in Phase 4; load a Bengali font now so Bangla names display properly.

## Q-19 — Migration

1. Migrate all 1,769 contacts, including the 1,194 unassigned historical ones? Default: yes, with the historical campaign at Low priority.
2. The six leads already marked Converted have no enrollment data. Default: migrate as Converted with a "legacy" note and no enrollment record.
3. Staff login phone numbers are not in the sheet. They must be collected.
4. Which course does each of the five campaigns belong to? Four of the five courses in the sheet are named "Spoken English" — are they one course?
5. Target go-live date, if any.

## Q-20 — Hosting and operations

1. Where will the application run? The engine, reminders and Lazy rule need a scheduler entry and a continuously running queue worker; basic shared hosting often cannot provide the second.
2. Is the GitHub repository public or private? (See W-SEC-01.)
3. Who is responsible for backups, and where are they kept?
4. Is there a staging copy for the migration rehearsal?
5. Customer data: how long is it kept, and who may export it?
