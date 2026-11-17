# Pedago Academy ERP — Analysis & Specification Pack

**Prepared:** 16 November 2026
**Basis:** everything under `public/user_defined_assets/` plus the full Laravel codebase at commit `85ef9a8` (branch `main`) and the local development database.

---

<!-- build-update -->

## Build update — 17 November 2026

The documents below were written against commit `85ef9a8`, before the build started. Since then the admin panel, the four role panels, the distribution engine, the password module and the in-app guide have been built. Each document now opens with a short "Build update" saying what changed for that document; the original analysis below it is kept as the baseline.

| Read this | For |
| :--- | :--- |
| [10_Implementation_Update.md](10_Implementation_Update.md) | Everything built since the analysis, rule by rule, with what is still open |
| [web/index.html](web/index.html) | The rules, logic and specifications as a browsable web page, with a distribution calculator |
| In the ERP: **Documentation** in the sidebar | The live rule book for admins and a training guide for every other role |

Headline changes: the sales engine now exists (distribution with hot/warm/cool/cold priority, batches, the executive task list, call feedback, follow-ups, transfers with team-leader approval, payment verification). Seven of the eight verified defects are fixed; phone-number normalisation is still open. **W-SEC-01 (customer data and PINs inside `public/` and on GitHub) is still open and is still the first thing to do.**

<!-- build-update -->


## 1. What is in this folder

| # | Document | What it answers |
| :-- | :--- | :--- |
| 00 | `00_README.md` (this file) | Summary, reading order, top priorities |
| 01 | `01_Requirements_Analysis.md` | What the source documents, the interim Apps Script system and the UI mockups actually require |
| 02 | `02_Current_System_Analysis.md` | What the Laravel project contains today — models, controllers, views, database, tests — and how complete it is |
| 03 | `03_SRS.md` | The consolidated Software Requirements Specification for the full ERP sales module |
| 04 | `04_Business_Logic_and_Algorithms.md` | Exact rules, state machines, the distribution algorithm, scheduled jobs, permission matrix |
| 05 | `05_Target_Database_Design.md` | Schema changes needed, new tables, indexes, and the Apps Script → ERP data migration map |
| 06 | `06_Weaknesses_and_Risks.md` | Every defect and weakness found, with severity, evidence and fix |
| 07 | `07_Inconsistencies.md` | Contradictions between documents, code, the interim system and the mockups, with a proposed resolution for each |
| 08 | `08_Build_Roadmap.md` | Phased build order with dependencies and exit criteria |
| 09 | `09_Open_Questions.md` | The four decisions made on 16 November 2026, and the questions still open, each with a recommended default |

Suggested reading order: **00 → 06 → 07 → 09**, then 03/04/05 as the build reference.

## 2. What was analysed

| Source | Detail |
| :--- | :--- |
| Requirement specs | `Sales_Lead_Distribution_Management_Module` v-01 (42 sections, Bangla), v-02 (15 sections), v-03 / 3.1 (15 sections, English, latest) |
| Database document | `db.md` — ER diagram, data dictionary, FK policies, trigger definitions |
| Password document | `Pass encrypt and decrypt formula.pdf` — reversible character transformation, v2.0 |
| Instruction file | `prompt.txt` — the original brief, including the password policy decision |
| Interim system | `temp_erp_appScript/Code.txt` (1,923 lines of Apps Script) and the exported sheets: 13 staff, 5 campaigns, 5 courses, 1,769 contacts, 2,260 inquiries, 1,294 activities |
| UI proposals | Admin dashboard, Lead upload, Login mockups |
| Codebase | Laravel 13 / PHP 8.5 / Blade + Tailwind v4 / Pest — 24 models, 16 controllers, 3 actions, 1 middleware, 21 migrations, 25 views, 62 routes |
| Tests | 154 tests, 791 assertions — all passing |
| Dev database | 7 employees, 2 teams, 1 shift, 6 campaigns, 5 courses, 0 leads |

## 3. Headline findings

**1. The foundation is built; the sales engine is not.**
Login and onboarding, employees, teams and duty times, the course catalogue, campaigns, lead upload, the duplicate-review queue, an all-leads list and two settings exist and are tested. None of the following exists yet: the distribution engine, lead batches, the sales executive's task screen, feedback, follow-ups, the Lazy rule, transfers, the enrollment screen, the customer payment portal, the accounts dashboard, installments, notifications, reports, or any scheduled job. The tables for these were created, but no code reads or writes them. Of the 152 requirements in the SRS, 28 are built (18%) and 23 more are partly built (15%); the modules that deliver the product's purpose have none working.

**2. The v-03 acceptance checklist is misleading.**
Section 15 of v-03 ticks nine of ten criteria as done. In the code, none of those nine is implemented. Treat that checklist as "specified", not "delivered".

**3. There are eight confirmed defects that need fixing before anything else.**
Each was reproduced against the code (details in document 06):

| ID | Problem | Severity |
| :-- | :--- | :--- |
| W-SEC-01 | Real customer phone numbers, live staff PINs and the password cipher key sit in the web-served `public/` folder and are committed to the GitHub repository | Critical |
| W-SEC-02 | Any sales executive can see and export every lead in the company | Critical |
| W-SEC-03 | A sales executive can request the Admin role and a team leader can approve it | Critical |
| W-SEC-04 | Any team leader or supervisor can rename, re-staff or delete any team | High |
| W-SEC-05 | A suspended employee stays logged in | High |
| W-BUG-01 | Quick-creating a lead whose phone already exists crashes with a server error | High |
| W-BUG-02 | `+8801711…`, `01711…` and `1711…` are stored as three different people, so duplicates slip through | High |
| W-SEC-08 | The only administrator can demote themselves, leaving the system with no admin | Medium |

**4. The password requirement is not what the code does.**
`prompt.txt` asks for reversible password storage using the PDF's algorithm so an admin can read staff passwords. The code and `db.md` use one-way hashing, and a test asserts it. You confirmed on 16 November 2026 that the PDF method is the one to use. Document 04 §16 gives the exact procedure with a verified test vector; document 06 (W-SEC-07) lists the method's known weaknesses and seven measures that reduce them — the first being a new key, because the one printed in the PDF is already in the repository.

**5. The interim Apps Script system solved real problems the ERP has not inherited.**
Phone normalisation, automatic handling of repeat inquiries (22% of all inquiries today), reactivation of closed leads, multiple secondary numbers, an "Invalid/Junk" status, lead priority, and a number-check tool for referrals all work in the sheet-based system and are missing from the ERP. Document 01 §4 lists them.

**6. The specification versions disagree with each other in places that change the build.**
The Lazy rule has three different definitions, the transfer approval rule has two, the batch-completion rule has two, and v-03 dropped several v-01 sections (reopen rules, first-action overdue, KPIs, notifications matrix) without saying whether they were removed on purpose. Document 07 lists 42 inconsistencies in all.

## 4. Decisions recorded on 16 November 2026

| Question | Decision | Effect on the documents |
| :--- | :--- | :--- |
| Q-01 Password storage | Reversible, by the PDF formula, with an admin-only decrypt screen | Procedure in 04 §16; data change in 05 §2.1; measures in 06 W-SEC-07 |
| Q-02 Repeat inquiries | Automatic rules with an inquiry log | Rules D0–D8 in 04 §7; tables in 05 §3 |
| Q-03 Batch slot of a converted lead | Held until Accounts verifies the payment | 04 §4.2 and §11.3; accepted risk R-1 in 06 |
| Q-04 Lazy rule | Either: a follow-up overdue past the next working shift, or a batch unfinished for two working days | 04 §9 |

Q-03 and Q-04 together raise one follow-on point, **Q-04a**: taken literally, an executive would be marked Lazy while a customer has not yet paid. The documents assume leads awaiting payment do not count towards Lazy; please confirm.

## 5. Recommended order of work

1. **Secure what exists** — move the data out of `public/`, rotate the PINs, fix the five access-control defects (document 06, Phase 0 in document 08).
2. **Answer the remaining questions** in document 09 — Q-04a to Q-07 affect the engine.
3. **Build the core sales loop** — availability, distribution, batches, task screen, feedback, follow-ups — then migrate the Apps Script data and retire the sheet.
4. **Add control features** — Lazy rule, transfers, reassignment, notifications, reports.
5. **Build conversion and finance** — enrollment, installments, customer portal, accounts verification, receipts, then payment gateways.

## 6. Conventions used in these documents

- **Requirement IDs** `FR-<module>-nn` and `NFR-nn` are defined in document 03.
- **Weakness IDs** `W-SEC`, `W-BUG`, `W-ARCH`, `W-PERF`, `W-QA`, `W-OPS` are defined in document 06.
- **Inconsistency IDs** `INC-nn` are defined in document 07; **question IDs** `Q-nn` in document 09.
- **Status labels:** *Built* (works and is tested), *Partial* (some of it works), *Schema only* (tables exist, no logic), *Not built*.
- **"Verified"** next to a finding means it was reproduced by running the application code, not only read.
- Code references are written as `path/to/file.php:line` relative to the project root.
- No staff PIN, customer phone number or key value is reproduced anywhere in these documents.

## 7. A note on this folder's location

You asked for these documents to be stored under `public/user_defined_assets/`. Everything under `public/` is served directly by the web server, so on a deployed instance these files — including the weakness list — would be readable by anyone who knows the URL. Finding W-SEC-01 recommends moving the whole `user_defined_assets` folder out of `public/` (for example to `docs/`); these documents should move with it.


---

## Build update 2 — 17 November 2026 (leave, follow-ups, notifications, conversion, interim script)

Since the first build update the application gained: a developer panel and database page, team leaders as sales people, KPIs and performance, leave under the Bangladesh Labour Act with a two-step approval chain, two follow-up stages with a notification centre for every role, the conversion flow (enroll, pay, verify), and every requirement of the interim Google Sheets script (`Code.txt` and `Code-2.txt`).

`10_Implementation_Update.md` holds the full record in sixteen numbered sections, 10 to 16 being the newest. The web version in `web/` explains the rules for non-technical readers. Documents 01 to 09 each carry this second update block, so read it after the original text.

Reading order for a new developer: `10_Implementation_Update.md`, then `05_Target_Database_Design.md` (schema and what exists), then `04_Business_Logic_and_Algorithms.md`.
