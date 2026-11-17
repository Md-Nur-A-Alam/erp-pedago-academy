# 01 — Requirements Analysis

Analysis of every source under `public/user_defined_assets/`: what each one says, how the requirements evolved, and what the interim system and mockups add that the written specs do not.

---

<!-- build-update -->

## Build update — 17 November 2026

Two requirements were added by the owner after this analysis and are now part of the specification:

| # | Rule | Where it is specified |
| :-- | :--- | :--- |
| BR-20 | Leads are ranked by freshness (hot, warm, cool, cold). Fresh leads are distributed and called first; one slot in four is kept for the longest-waiting lead. | Document 04 §17, document 10 §2 |
| BR-21 | Every role has a training guide inside the ERP; administrators also get the full rule book. | Document 10 §6 |

The password document (§5) is now implemented as decided. The mockup elements listed in §6.2 as "not built" are built, except the notification inbox.

<!-- build-update -->


## 1. Source inventory

| Source | Language | Role | Reliability |
| :--- | :--- | :--- | :--- |
| Spec v-01 | Bangla + English terms | Original business requirement — 42 sections | Most complete on operations (KPIs, reopen, leave, notifications) |
| Spec v-02 | Bangla | Adds EdTech duplicates, shifts, conversion and payment | Bridges v-01 and v-03 |
| Spec v-03 (3.1) | English | Latest. Adds scarcity distribution, Lazy rule, dual-approval transfers, installments, course batches, SQL schema | Authoritative where it speaks; silent on many v-01 topics |
| `db.md` | English | ER diagram and data dictionary | Closest to the real schema, but partly outdated |
| Password PDF | English | Reversible cipher definition | Conflicts with `db.md` and the code |
| `prompt.txt` | English | Original instruction: build models; use the PDF cipher; admin-only decrypt module | States the password policy decision |
| `Code.txt` + sheets | Bangla UI, English code | The system the team uses every day | Ground truth for how sales actually works now |
| UI mockups | English | Visual direction for dashboard, upload, login | Reference mockups with sample data |

**Rule used in this pack:** v-03 wins where it is explicit. Where v-03 is silent, v-02 then v-01 apply. Where the interim system contradicts the specs, the difference is raised as a question rather than decided silently.

---

## 2. How the requirements evolved

### 2.1 Version-by-version

| Topic | v-01 | v-02 | v-03 |
| :--- | :--- | :--- | :--- |
| Purpose | Distribute leads, ownership, follow-up, prevent conflict | + automate enrollment and installments | + payment gateways, accounts reconciliation |
| Roles | Admin, Supervisor, Team Leader/Sales Manager, Sales Executive | + Accounts | Same five. Marketing Executive appears only in `db.md` and the code |
| Batch size n | 3, "currently" | Default 3, only Admin changes it | Global setting `default_batch_size_n` |
| Distribution | Round robin / balanced, n per executive | Strict batch of n | Scarcity model: n → 2 → 1-by-1 round robin by longest idle |
| Engine switch | — | — | Master ON/OFF toggle |
| Working window | One office time, cut-off 30 min before end | Per-employee shift and roster, cut-off 30 min | Same, as `shift_cutoff_minutes` |
| Availability | Available, Busy, Unavailable, Off Duty, Lazy, Resign | Same | Same, defined as a state machine |
| Lazy | Busy for 2+ consecutive days | Batch incomplete or busy for 2+ working days | Follow-up overdue beyond threshold t (24 h or 2 working days) |
| Duplicates | Phone match; admin decides | Smart matrix: definite vs review queue | Same matrix, four admin actions |
| Follow-up | Separate queue, date and time mandatory | Leaving the batch frees the slot | Same, "queue isolation" |
| First-action overdue | Lead flagged if no first action within t | Not mentioned | Not mentioned |
| Transfer | With own TL or supervisor approval, reason mandatory | Same | Both TLs must approve; never frozen; auto-approve after 7 days; admin override |
| Reassignment | TL/Admin, recorded | Same | Mentioned only in the role table |
| Auto-reassignment | Optional, phase 3 | — | — |
| Lead search | Executive may check a number that is in own leads | Same | No free search; in-context "past inquiries" button only |
| Conversion | Store date, course, executive, payment reference | Enrollment form, secure link, portal, payment, accounts approval | + installment T−2 follow-ups, batch seat allocation, slot held until payment verified |
| Payment channels | — | bKash, SSLCommerz, AamarPay, manual | bKash, SSLCommerz, manual |
| Reopen | Admin/TL may reopen closed leads with reason | — | — |
| Leave / absence | TL sees and moves leads | Reassign on leave | Role table only |
| Deactivation | Leads go to reassignment queue | Leads rerouted | Unworked leads to central queue |
| Dashboards | 10 metrics listed | — | Not listed |
| KPIs | 8 KPIs listed | — | Not listed |
| Notifications | Matrix per role | "Alert" mentions | Alerts to TL/Supervisor on Lazy; T−2 reminders |
| Search and filter | 11 filter fields for Admin/TL | — | — |
| History | Immutable, correction by new entry | Immutable | Immutable, DB triggers in `db.md` |
| Architecture | — | Breeze, Sanctum, `app/Modules/Lead`, Blade components | `app/Modules/Lead` services, jobs, gateways |
| Phasing | 3 phases defined | — | "Phase 1" mentioned for ingestion and payments |

### 2.2 Requirements present in v-01 but absent from v-03

These were never explicitly withdrawn. This pack keeps them in the SRS and marks each for confirmation (Q-14).

- First-action overdue: a lead with no action within time t after assignment is flagged (v-01 §21).
- Reopen rules for Converted, Lost and Not Interested leads (v-01 §36).
- Correction notes instead of editing history (v-01 §37).
- Dashboard metric list (v-01 §29) and sales KPI list (v-01 §30).
- Source and campaign reporting (v-01 §31).
- Admin/TL search and filter fields (v-01 §32).
- Notification matrix (v-01 §33).
- Leave and absence handling (v-01 §27).
- Ownership protection record list (v-01 §24): created by, assigned by, first contacted by, previous owners, conversion owner.
- Auto-reassignment as a later phase (v-01 §23).

---

## 3. Consolidated business rules

The rules every version agrees on, or that v-03 settles.

| # | Rule | Source |
| :-- | :--- | :--- |
| BR-01 | One lead has exactly one active owner at any moment | all |
| BR-02 | Leads are handed out in batches of up to n; n is a global setting, default 3, changed only by Admin | v-02, v-03 |
| BR-03 | A new batch is issued only when every item of the current batch is completed | all |
| BR-04 | A batch item is completed only by submitting feedback with a next action | all |
| BR-05 | Next action "Follow-up" requires a date and time, moves the lead to the follow-up queue and frees the batch slot | v-02, v-03 |
| BR-06 | No new batch is issued within the cut-off window before an executive's shift end; unfinished work carries forward | all |
| BR-07 | A sales executive sees and acts on only their own leads, enforced on the server | all |
| BR-08 | Team leader sees own team; supervisor sees assigned teams; admin sees everything | all |
| BR-09 | Every action is recorded; history cannot be edited or deleted | all |
| BR-10 | Duplicates of one customer must never be active with two executives at once | v-01 |
| BR-11 | The same phone with a different course or a different student is a possible valid inquiry and goes to review | v-02, v-03 |
| BR-12 | Transfers require a reason and approval; ownership changes only when the transfer completes | all |
| BR-13 | A lead under a pending transfer stays fully workable by the current owner | v-03 |
| BR-14 | A resigned or deactivated executive never receives leads; their leads are not lost | all |
| BR-15 | Conversion creates an enrollment with fee, discount and an installment plan | v-02, v-03 |
| BR-16 | A manual payment needs a transaction ID or a slip image, or both | v-03 |
| BR-17 | Accounts verification completes the sale; rejection returns it to the executive with a reason | v-02, v-03 |
| BR-18 | Converting reserves a seat in the lowest-numbered course batch with free capacity; a full course blocks conversion | v-03 §9.3 |
| BR-19 | Unassigned, unworked and overdue leads are always visible to management | v-01 |

---

## 4. The interim Apps Script system

This is the only system with real usage, so its behaviour and its bug history are requirements evidence.

### 4.1 What it is

A Google Sheet with seven tabs (Staff, Campaigns, Courses, Settings, Contacts, Inquiries, Activities) and an Apps Script web app. Roles: Admin, Manager, MarketingExecutive, SalesExecutive. The interface is in Bangla.

### 4.2 Data model

| Sheet | Columns | Meaning |
| :--- | :--- | :--- |
| Staff | StaffID, Name, PIN, Role, Team, Active, IsOnline | Login is name + numeric PIN stored in plain text |
| Campaigns | CampaignID, CampaignName, Source, DistributionMode, Active | Mode is Auto or Manual |
| Courses | CourseID, CourseName | Name only |
| Settings | OfficeStart, OfficeEnd, WeeklyOffDay, CutoffMinutes | One office schedule for everyone |
| Contacts | ContactID, Name, Phone, District, Status, AssignedTo, UnassignedReason, CampaignID, CourseID, CreatedAt, CreatedBy, LastActivityAt, NextFollowUpDate, Notes, Age, SecondaryPhones, Priority | **One row per person**, keyed by phone |
| Inquiries | InquiryID, ContactID, CampaignID, Source, CourseID, SubmittedAt, CreatedBy | **One row per form submission**; many per contact |
| Activities | ActivityID, ContactID, StaffID, StaffName, Timestamp, CallStatus, Notes, StatusAfter, NextFollowUpDate | Call log and system notes |

The key structural point: the interim system separates the **person** (Contact) from the **inquiry event** (Inquiry). The ERP specs and schema have only `leads`.

### 4.3 Behaviour

| Area | What the script does |
| :--- | :--- |
| Phone normalisation | Strips symbols; `+880` / `880` → `0`; a 10-digit number gets its lost leading zero back; valid Bangladeshi mobile is 11 digits starting with 0; anything else of 8–15 digits is stored as `+<digits>` |
| Duplicate match | Against the primary phone **and** every secondary phone |
| New number | Creates a Contact and an Inquiry, then runs assignment |
| Existing number, lead closed | **Reactivates** the lead, returns it to the previous owner if still active, logs an Inquiry |
| Existing number, lead open | Logs an Inquiry only. If the last touch was on an earlier day, sets the follow-up date to today so it resurfaces |
| Assignment on arrival | Manual-mode campaign → Unassigned queue. Otherwise the online, in-hours executive with the fewest open leads whose unactioned count is below 3 |
| Batch refill | On login, and after any feedback: if the executive has zero leads in status New, give up to 3 oldest queue leads |
| Availability | Active + logged in + inside the office window; weekly off day and cut-off minutes come from Settings |
| Feedback | Call status, notes, new status, next follow-up date, optional priority, optional name correction |
| Feedback guards | Status can never return to New. A connected call that leaves the lead open must have a follow-up date |
| My Tasks | Open leads that are New, or have no follow-up date, or are due or overdue — minus anything already worked today. Sorted: overdue, new, due today, no plan; then by priority |
| Statuses | New, Unassigned, Contacted, Interested, Follow-up, Converted, Not Interested, Unreachable, Lost, Invalid/Junk |
| Call statuses in data | Connected, No Answer, Not Connected, Busy, Switched Off, Wrong Number, Note |
| Secondary phones | Add or remove alternate numbers; blocked if the number belongs to another contact; logged as a note |
| Number check | A sales executive can look up a phone and see only owner, status, campaign and created date |
| Self-added leads | A sales executive can add a referral lead that is assigned to themselves |
| Reassignment | Manager or Admin only, logged as a note |
| Reports | Campaign totals and conversion rate; per-executive totals, open, overdue, conversion rate, with a date-range filter |

### 4.4 Real data profile (export in the folder)

| Measure | Value | What it tells us |
| :--- | :--- | :--- |
| Contacts | 1,769 | Current volume to migrate |
| Inquiries | 2,260 | 491 repeat inquiries — **22% of submissions are repeats**, all handled automatically today |
| Activities | 1,294 | 975 real call logs, 319 system notes |
| Unassigned | 1,194 (67%) | Mostly the 1,500 historical leads imported under one campaign |
| Statuses of worked leads | Unreachable 164, Contacted 123, Not Interested 96, Follow-up 49, New 44, Interested 40, Invalid/Junk 28, Lost 25, Converted 6 | Unreachable is the largest outcome; Invalid/Junk is in real use |
| Converted | 6 of 575 worked (≈1%) | Conversion tracking has barely started |
| Course filled in | 53 of 1,769 (3%) | Course is almost never captured at intake |
| District filled in | 177 (10%) | Location is rarely captured |
| Age filled in | 1,407 (80%); 98% of those are aged 3–13 | Customers are parents of young children — "guardian" is the real contact |
| Priority used | 11 leads | The feature exists but is barely used |
| Staff | 2 Admin, 1 Marketing, 10 Sales (2 inactive) | No teams are set — the Team column is empty for everyone |
| Courses | 5 rows, four of them named "Spoken English" | Course master data is duplicated |
| IsOnline | "Yes" for every staff member, including inactive ones | Logout is never pressed; the online flag is unreliable |
| CutoffMinutes | Blank in the live sheet | The cut-off silently falls back to the code default |

### 4.5 Lessons recorded in the script's own comments

The script documents the incidents that shaped it. Each is a requirement for the ERP.

| Incident | Cause | Requirement for the ERP |
| :--- | :--- | :--- |
| Same lead created twice | Spreadsheet turned the phone into a number and dropped the leading zero | Canonical phone normalisation on every entry path; restore a lost leading zero |
| Assignment window "permanently closed" | A time setting was re-typed and stored in another format | Typed, validated settings — never free text |
| 500-row import timed out at row 80 | The whole sheet was re-read for every row | Bulk import must work in sets, not row by row |
| Batches stuck | Feedback could set the status back to New | Status transitions must be validated on the server |
| Leads beyond the batch cap | Assignment paths that ignored the cap | One assignment service; no path may bypass it |
| My Tasks showing 20–30 leads per person | Open leads with no follow-up date | An open, contacted lead must always have a next action date |
| Dead-end leads | Connected and still open but nothing scheduled | Same rule, enforced on the server |
| Number check returning nothing | Formatting differences in stored numbers | Compare normalised values only |
| Duplicate contacts needing merge | Earlier bugs | A merge tool that moves history and keeps the oldest record |

### 4.6 Capabilities in the interim system that the ERP specs do not cover

| Capability | In v-03? | In ERP code? | Recommendation |
| :--- | :-- | :-- | :--- |
| Contact / Inquiry separation | No | No | Add an inquiry log (decided, Q-02) |
| Automatic handling of repeat inquiries | No — they go to manual review | No | Add automatic rules, or admin reviews ~22% of volume by hand |
| Reactivation of closed leads to the previous owner | No | No | Add (decided, Q-02) |
| Multiple secondary phones | No — one alternate number | No | Add a phone table |
| Invalid/Junk status | No | No | Add status "Invalid" |
| Lead priority | No | No | Add (low cost) |
| Name correction during feedback | No | No | Add, with history |
| Number check for executives | v-03 forbids free search | No | Decide (Q-12) |
| Executive self-adds a referral lead | No | No | Decide (Q-12) |
| Weekly off day | Mentioned as "weekly day off" | No | Add to shifts |
| Per-campaign Auto/Manual mode | Not in v-03 text; in `db.md` | Column exists, unused | Wire into the engine |
| Manager role (sales + marketing) | No | No | Map to Supervisor or Team Leader (Q-09) |

---

## 5. Password document

The PDF defines a reversible transformation over bytes: reverse the text, add each byte to its predecessor and its position (mod 256), then add a repeating key byte (mod 256). Decryption reverses the steps. The key is a fixed text printed in the PDF.

`prompt.txt` states the policy: store the transformed password; give Admin a decrypt module so an admin can open any staff account; the company considers this acceptable.

Observations:

1. `db.md` describes `users.password` as a "one-way password hash", and the code hashes with bcrypt (`app/Models/User.php:24`). A test named "stores user passwords as one-way hashes" asserts it. So the stated policy was not implemented.
2. The PDF's own section 8 states the method "should not be used to protect sensitive information" and recommends established authenticated encryption.
3. The output is raw bytes 0–255, so it must be encoded (hex or Base64) before it can be stored in a text column. The PDF does not specify this.
4. On 16 November 2026 the PDF method was confirmed as the one to use (Q-01). The exact procedure, with the encoding and a test vector, is in document 04 §16; its weaknesses and the measures that reduce them are W-SEC-07 in document 06.

---

## 6. UI/UX proposals

Three mockups, all marked as reference designs with sample data.

### 6.1 Design system

| Token | Value | In the app? |
| :--- | :--- | :-- |
| Brand blue / dark | `#233F96` / `#1A2F73` | Yes — `resources/css/app.css` |
| Tint, ink, muted, canvas, line | `#E8EDFA`, `#14203F`, `#55617D`, `#F3F5FA`, `#DCE1EE` | Yes |
| Red, amber, green with tints | `#C92E18`, `#7A4F00`, `#0F6845` | Yes |
| Font — dashboard and upload | IBM Plex Sans, IBM Plex Mono for numbers, Noto Sans Bengali | No — the app loads Poppins only |
| Font — login | Poppins, Noto Sans Bengali | Poppins yes, Bengali no |
| Status pills | Available green, Busy blue, Lazy red, Unavailable amber, Off duty grey | Partly |

### 6.2 Admin dashboard mockup

| Element | Requirement it implies | Built? |
| :--- | :--- | :-- |
| Sidebar: Dashboard, Reports | Reports module | Reports not built |
| Sidebar: Central queue, Duplicate review, Follow-ups (overdue badge), Transfers | Four operational queues with live counts | Only duplicate review |
| Sidebar: Payments review, Installments | Finance module | Not built |
| Sidebar: Teams & roster, Employees, Courses, Settings | People and setup | Built |
| Top bar: search by phone, name or lead code | Global search for Admin | Search exists on the leads page only |
| Top bar: "Distribution engine: ON" switch | Engine toggle | Not built |
| Top bar: Upload leads | Shortcut | Built |
| KPI tiles: unassigned in queue, active batches, overdue follow-ups (Lazy count), payments under review, pending transfers, converted today with amount | Six live metrics | Tiles exist; most read tables that are always empty |
| "Distribution engine — next cycle" panel: L, S, rule, per-executive plan | Preview of the next distribution | Not built |
| Pipeline by status | Funnel | Built for Admin |
| Executive roster today: team, shift, status, batch progress, overdue, action | Live roster | Partial — shows override rows only |
| "Needs your attention": payments to verify, transfers awaiting approval with day counter, installments due in 2 days | Action inbox | Not built |

### 6.3 Lead upload mockup

| Element | Requirement it implies | Built? |
| :--- | :--- | :-- |
| Tabs: Bulk import / Single lead | Two entry modes | Built |
| Step 1: channel, then campaign filtered by channel | Dependent dropdowns | Built |
| "Default course" taken from the campaign and attached to every row | Lead inherits the campaign's course | **Not built** — imported leads get no course (W-BUG-05) |
| Step 2: choose optional columns; template updates | Column picker | Built (CSV) |
| Step 3: drop an `.xlsx` file | Excel upload | **CSV only** |
| Result: valid / to duplicate review / invalid phone, then "Import N valid leads" and "Download error rows" | Preview, partial import, error file | **Not built** — one bad row rejects the whole file |
| "Exact duplicates are suppressed" | Automatic definite-duplicate rule | **Not built** — every match goes to review |
| Channel list: 7 channels | — | The app has 11 |

### 6.4 Login mockup

Phone number and password, "Remember me", "Forgot Password?" and a hero image. Built, except **Forgot Password** — there is no reset flow of any kind, and an admin has no screen to reset an employee's password either (W-SEC-06).

---

## 7. Requirement gaps in the source documents

Topics no document specifies, but the system cannot be built without. Each is carried into the SRS with a proposed rule and listed in document 09.

| Gap | Why it matters |
| :--- | :--- |
| Is the lead queue global or per team? | Decides who can receive which lead |
| Does an executive need to be present (logged in or checked in) to receive leads? | Roster-only assignment gives leads to absent staff |
| What orders the queue — oldest first? campaign priority? | Fairness and response time |
| How many failed attempts before "Unreachable"? | The largest outcome category in real data |
| What happens to follow-up leads of a deactivated executive? | Worked leads should not be redistributed blindly |
| Same-team transfers — who approves? | v-03 covers cross-team only |
| Who approves when a team has no leader, or one TL leads both sides? | Otherwise transfers stall |
| Is the lead's team the owner's team, always? | Reports and TL visibility depend on it |
| Can team leaders or supervisors own and call leads? | The assign dropdown lists them |
| Discount limits and approval | Money control |
| Installment rules — minimum first payment, maximum count, date limits | Validation of the enrollment form |
| How long an unpaid enrollment may hold a seat | Seats can be blocked indefinitely |
| Refunds, cancellations after payment, batch change | Finance completeness |
| Money receipt format, numbering and delivery channel | Legal and accounting |
| SMS / WhatsApp / email provider | "System sends the link" needs a provider |
| Interface language | Staff use a Bangla interface today |
| Data retention and customer privacy | Thousands of parents' phone numbers |
| Audit requirements for admin actions | Especially if admins can enter staff accounts |
| Under Age / Over Age outcome | Listed as a call status but it is a disqualification |


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

Requirements raised after the first analysis, and where they stand:

| Requirement | State |
| :--- | :--- |
| Team leaders are sales people (batches, tasks, follow-ups, KPIs, leave) | Built |
| KPI and performance pages for every role | Built |
| Leave by the Bangladesh Labour Act: casual, sick, annual, maternity, unpaid; Friday not counted | Built |
| Leave approval: executive needs team leader then supervisor or admin; others one higher authority; admin none | Built |
| Follow-up in two stages, coming up (30 hours before) and overdue (until an outcome) | Built, window is a setting |
| Notifications for every role, by need | Built |
| Dashboards show both coming-up and overdue follow-ups | Built |
| Conversion way: enroll, installment plan, payment, verification | Built |
| Priority High, Medium, Low on a lead | Built |
| Invalid/Junk status; wrong number closes the lead | Built |
| Several numbers per lead and a number check | Built |
| Repeat inquiry log and automatic reopen | Built |
| Per-person Auto or Manual mode and a daily lead limit | Built |
| Guardian name and standing remark per lead | Built |
| WhatsApp thank-you message | Built |
| Paste import, merge duplicates, data health | Built |
| Developer: full access, database map with ER diagram | Built |

Still open: the customer payment page, online gateways, receipts, data migration from the sheet, and a public inquiry form.
