# Pedago Academy ERP — Database ER Diagram & Data Dictionary

**Database Target:** MySQL 8.x (`pedago_erp_db`)  
**Context:** Pedago Academy ERP employee onboarding and sales management
**Encoding:** `utf8mb4_unicode_ci`  
**Date:** November 2026 (Updated with phone login, employee profiles, approval workflow, and course batches)

---

## 1. Visual Entity Relationship (ER) Diagram

```mermaid
erDiagram
    USERS ||--|| EMPLOYEES : "has_employee_record (1:1)"
    EMPLOYEES ||--|| EMPLOYEE_DETAILS : "has_profile (1:1)"
    EMPLOYEES ||--o{ EMPLOYEE_CHANGE_REQUESTS : "requests_changes (1:N)"
    EMPLOYEES ||--o{ TEAMS : "leads (1:1 optional)"
    EMPLOYEES ||--o{ SUPERVISOR_TEAMS : "supervises (1:N)"
    TEAMS ||--o{ SUPERVISOR_TEAMS : "supervised_by (1:N)"
    EMPLOYEES ||--o{ EMPLOYEE_ROSTERS : "has_roster (1:N)"
    
    LEADS_SOURCE ||--o{ LEADS : "originates_from (1:N)"
    COURSES ||--o{ LEADS_SOURCE : "runs_campaigns (1:N)"
    TEAMS ||--o{ LEADS : "assigned_to_team (1:N)"
    EMPLOYEES ||--o{ LEADS : "owns_currently (1:N)"
    COURSES ||--o{ LEADS : "interested_in (1:N)"
    
    EMPLOYEES ||--o{ LEAD_BATCHES : "receives_batch (1:N)"
    LEAD_BATCHES ||--o{ LEAD_BATCH_ITEMS : "contains_items (1:N)"
    LEADS ||--o{ LEAD_BATCH_ITEMS : "batched_in (1:N)"
    
    LEADS ||--o{ LEAD_FEEDBACKS : "has_activity_history (1:N)"
    EMPLOYEES ||--o{ LEAD_FEEDBACKS : "logged_by (1:N)"
    
    LEADS ||--o{ LEAD_FOLLOWUPS : "scheduled_followups (1:N)"
    EMPLOYEES ||--o{ LEAD_FOLLOWUPS : "assigned_to (1:N)"
    
    LEADS ||--o{ LEAD_TRANSFERS : "transferred_record (1:N)"
    EMPLOYEES ||--o{ LEAD_TRANSFERS : "from_employee (1:N)"
    EMPLOYEES ||--o{ LEAD_TRANSFERS : "to_employee (1:N)"
    TEAMS ||--o{ LEAD_TRANSFERS : "from_team (1:N)"
    TEAMS ||--o{ LEAD_TRANSFERS : "to_team (1:N)"
    EMPLOYEES ||--o{ LEAD_TRANSFERS : "sender_tl_approved (1:N)"
    EMPLOYEES ||--o{ LEAD_TRANSFERS : "receiver_tl_approved (1:N)"
    
    LEADS ||--|| ENROLLMENTS : "converts_to (1:1)"
    COURSES ||--o{ ENROLLMENTS : "enrolled_course (1:N)"
    EMPLOYEES ||--o{ ENROLLMENTS : "converted_by (1:N)"
    SUBJECTS ||--o{ COURSE_TOPICS : "groups_topics (1:N)"
    SUBJECTS ||--o{ COURSES : "classifies (1:N)"
    COURSE_TOPICS ||--o{ COURSES : "curriculum_topic (1:N)"
    COURSES ||--o{ COURSE_BATCH : "offered_in_batches (1:N)"
    COURSE_BATCH ||--o{ ENROLLMENTS : "enrolls_students (1:N optional)"
    
    ENROLLMENTS ||--o{ INSTALLMENT_SCHEDULES : "has_installments (1:N)"
    
    ENROLLMENTS ||--o{ PAYMENTS : "has_payments (1:N)"
    INSTALLMENT_SCHEDULES ||--o{ PAYMENTS : "pays_installment (1:N optional)"
    EMPLOYEES ||--o{ PAYMENTS : "verified_by_accounts (1:N)"
    
    LEADS ||--o{ DUPLICATE_LEAD_REVIEWS : "matches_lead (1:N)"
    EMPLOYEES ||--o{ DUPLICATE_LEAD_REVIEWS : "reviewed_by (1:N)"

    USERS {
        BIGINT id PK "auto_increment"
        VARCHAR phone_number UK "25, unique, nullable for legacy accounts"
        VARCHAR password "hashed, not null"
        BOOLEAN must_change_password "default true"
        VARCHAR remember_token "100, null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    EMPLOYEES {
        BIGINT id PK "auto_increment"
        BIGINT user_id FK UK "users.id, nullable, unique"
        VARCHAR office_id UK "50, unique, nullable for legacy records"
        VARCHAR name "255, not null"
        VARCHAR designation "100, null"
        DATE joining_date "null"
        ENUM role "Admin, Supervisor, Team Leader, Sales Executive, Accounts, MarketingExecutive"
        ENUM status "Active, Inactive, Suspended"
        BIGINT shift_id FK "shifts.id, null"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    EMPLOYEE_DETAILS {
        BIGINT id PK "auto_increment"
        BIGINT employee_id FK UK "employees.id, unique"
        VARCHAR email UK "optional personal email"
        VARCHAR secondary_contact_number "25, null"
        VARCHAR emergency_contact_name "150, null"
        VARCHAR emergency_contact_number "25, null"
        DATE dob "null"
        TEXT nid_number "encrypted at application level, null"
        VARCHAR blood_group "10, null"
        TEXT present_address "null"
        TEXT permanent_address "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    EMPLOYEE_CHANGE_REQUESTS {
        BIGINT id PK "auto_increment"
        BIGINT employee_id FK "employees.id, not null"
        BIGINT requested_by_employee_id FK "employees.id, not null"
        JSON requested_changes "essential fields awaiting approval"
        TEXT reason "null"
        ENUM status "Pending, Approved, Rejected"
        BIGINT reviewed_by_employee_id FK "employees.id, null"
        TEXT review_note "null"
        TIMESTAMP reviewed_at "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    TEAMS {
        BIGINT id PK "auto_increment"
        VARCHAR name "100, not null"
        BIGINT team_leader_id FK "employees.id, null"
        BOOLEAN is_active "default true"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    SUPERVISOR_TEAMS {
        BIGINT id PK "auto_increment"
        BIGINT supervisor_id FK "employees.id, not null"
        BIGINT team_id FK "teams.id, not null"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    EMPLOYEE_ROSTERS {
        BIGINT id PK "auto_increment"
        BIGINT employee_id FK "employees.id, not null"
        BIGINT shift_id FK "shifts.id, not null"
        DATE roster_date "not null"
        BOOLEAN is_on_duty "default false"
        ENUM availability_status "Available, Busy, Unavailable, Off Duty, Lazy, Resigned"
        TIMESTAMP last_active_at "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
        UNIQUE employee_roster_date "employee_id, roster_date"
    }

    COURSES {
        BIGINT id PK "auto_increment"
        VARCHAR title "150, not null"
        VARCHAR code UK "50, unique, not null"
        TEXT description "course descriptions, null"
        TEXT conditions "course admission terms, null"
        BIGINT subject_id FK "subjects.id, nullable; required by course forms"
        BIGINT course_topic_id FK "course_topics.id, nullable; required by course forms"
        ENUM version "Bangla, English"
        VARCHAR category "100, null"
        DECIMAL base_fee "10,2, not null"
        BOOLEAN is_active "default true"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    SUBJECTS {
        BIGINT id PK "auto_increment"
        VARCHAR name UK "100, unique, not null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    COURSE_TOPICS {
        BIGINT id PK "auto_increment"
        BIGINT subject_id FK "subjects.id, not null"
        VARCHAR name "100, not null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
        UNIQUE subject_topic "subject_id, name"
        UNIQUE subject_topic_fk "subject_id, id"
    }

    COURSE_BATCH {
        BIGINT id PK "auto_increment"
        BIGINT course_id FK "courses.id, not null"
        INT batch_number "unsigned, unique per course"
        VARCHAR batch_name "100, not null"
        VARCHAR short_code "30, unique per course"
        INT capacity "unsigned, not null"
        BOOLEAN is_latest "default false"
        DATE start_date "not null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
        UNIQUE course_batch_number "course_id, batch_number"
        UNIQUE course_batch_code "course_id, short_code"
        UNIQUE course_batch_fk "course_id, id"
    }

    LEADS_SOURCE {
        BIGINT source_id PK "auto_increment"
        ENUM channel "fb, wp, googleads, gptads, googleform, reference, website"
        VARCHAR campaign_name "150, null"
        BIGINT course_id FK "courses.id, nullable for legacy/unattributed source records"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    LEADS {
        BIGINT id PK "auto_increment"
        BIGINT source_id FK "leads_source.source_id, not null"
        VARCHAR student_name "150, not null"
        VARCHAR guardian_name "150, null"
        VARCHAR contact_number "25, not null, index"
        VARCHAR whatsapp_number "25, null"
        VARCHAR alternative_contact_number "25, null, index"
        VARCHAR email "120, null"
        INT age "null"
        VARCHAR class "50, null"
        BIGINT interested_course_id FK "courses.id, null"
        ENUM status "New, Assigned, Contacted, Follow-up, Interested, Enrolled - Pending Payment, Payment Under Review, Converted, Not Interested, Unreachable, Lost"
        BIGINT current_owner_id FK "employees.id, null"
        BIGINT team_id FK "teams.id, null"
        TIMESTAMP created_at
        TIMESTAMP edited_at "null"
        BIGINT edited_by FK "employees.id, null"
    }

    LEAD_BATCHES {
        BIGINT id PK "auto_increment"
        BIGINT employee_id FK "employees.id, not null"
        INT batch_size "default 3, not null"
        ENUM status "Active, Completed"
        TIMESTAMP assigned_at
        TIMESTAMP completed_at "null"
    }

    LEAD_BATCH_ITEMS {
        BIGINT id PK "auto_increment"
        BIGINT batch_id FK "lead_batches.id, not null"
        BIGINT lead_id FK "leads.id, not null"
        BOOLEAN is_completed "default false"
        VARCHAR action_taken "50, null"
        TIMESTAMP completed_at "null"
    }

    LEAD_FEEDBACKS {
        BIGINT id PK "auto_increment"
        BIGINT lead_id FK "leads.id, not null"
        BIGINT employee_id FK "employees.id, not null"
        VARCHAR call_status "50, not null"
        VARCHAR customer_interest "50, not null"
        TEXT customer_concern "null"
        TEXT sales_note "not null"
        VARCHAR next_action "50, not null"
        TIMESTAMP action_date "null"
        TIMESTAMP created_at
    }

    LEAD_FOLLOWUPS {
        BIGINT id PK "auto_increment"
        BIGINT lead_id FK "leads.id, not null"
        BIGINT employee_id FK "employees.id, not null"
        DATETIME followup_date_time "not null, index"
        TEXT note "null"
        BOOLEAN is_done "default false"
        BOOLEAN overdue_alert_sent "default false"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    LEAD_TRANSFERS {
        BIGINT id PK "auto_increment"
        BIGINT lead_id FK "leads.id, not null"
        BIGINT from_employee_id FK "employees.id, not null"
        BIGINT to_employee_id FK "employees.id, not null"
        BIGINT from_team_id FK "teams.id, not null"
        BIGINT to_team_id FK "teams.id, not null"
        TEXT reason "not null"
        BIGINT sender_tl_employee_id FK "employees.id, null"
        TIMESTAMP sender_tl_approved_at "null"
        BIGINT receiver_tl_employee_id FK "employees.id, null"
        TIMESTAMP receiver_tl_approved_at "null"
        BOOLEAN is_auto_transferred "default false"
        BOOLEAN is_admin_override "default false"
        ENUM status "Pending, Approved, Rejected"
        TIMESTAMP transferred_at "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    ENROLLMENTS {
        BIGINT id PK "auto_increment"
        BIGINT lead_id FK "leads.id, unique, not null"
        BIGINT course_id FK "courses.id, not null"
        BIGINT course_batch_id FK "course_batch.id, nullable; batch must belong to course_id"
        BIGINT sales_executive_id FK "employees.id, not null"
        VARCHAR secure_uuid UK "64, unique, not null"
        VARCHAR student_name "150, not null"
        VARCHAR student_phone "25, not null"
        VARCHAR student_email "120, null"
        VARCHAR guardian_name "150, null"
        VARCHAR guardian_phone "25, null"
        DECIMAL base_course_fee "10,2, not null"
        DECIMAL discount_percentage "5,2, default 0.00"
        DECIMAL final_deal_amount "10,2, not null"
        INT total_installments "default 1"
        ENUM status "Pending Payment, Payment Under Review, Enrolled, Cancelled"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    INSTALLMENT_SCHEDULES {
        BIGINT id PK "auto_increment"
        BIGINT enrollment_id FK "enrollments.id, not null"
        INT installment_number "not null"
        DECIMAL due_amount "10,2, not null"
        DATE due_date "not null, index"
        BOOLEAN followup_generated "default false"
        ENUM status "Pending, Under Review, Paid, Overdue"
        TIMESTAMP paid_at "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    PAYMENTS {
        BIGINT id PK "auto_increment"
        BIGINT enrollment_id FK "enrollments.id, not null"
        BIGINT installment_schedule_id FK "installment_schedules.id, null"
        DECIMAL amount "10,2, not null"
        TIMESTAMP payment_date "null"
        ENUM payment_method "bKash, SSLCommerz, Manual Bank, Manual MFS"
        VARCHAR transaction_id "100, null"
        VARCHAR slip_screenshot_path "255, null"
        BIGINT verified_by_employee_id FK "employees.id, null"
        ENUM status "Pending, Verified, Rejected"
        TEXT rejection_reason "null"
        TIMESTAMP verified_date "null"
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    DUPLICATE_LEAD_REVIEWS {
        BIGINT id PK "auto_increment"
        JSON incoming_lead_data "not null"
        BIGINT matched_lead_id FK "leads.id, not null"
        VARCHAR match_reason "255, not null"
        ENUM status "Pending Review, Approved New, Merged, Discarded"
        BIGINT reviewed_by_employee_id FK "employees.id, null"
        TIMESTAMP reviewed_at "null"
        TIMESTAMP created_at
    }

    SYSTEM_SETTINGS {
        BIGINT id PK "auto_increment"
        VARCHAR key UK "100, unique, not null"
        TEXT value "not null"
        VARCHAR description "255, null"
        TIMESTAMP updated_at
    }
```

---

## 2. Comprehensive Entity Data Dictionary

### 2.1 Entity Set: `USERS`
Authentication credentials only. A user can sign in with a unique phone number and password.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Login account ID |
| `phone_number` | `VARCHAR(25)` | YES | **UK** | NULL for legacy rows | Normalized login phone number |
| `password` | `VARCHAR(255)` | NO | None | None | One-way password hash |
| `must_change_password` | `BOOLEAN` | NO | None | `TRUE` | Force temporary password change on first sign-in |
| `remember_token` | `VARCHAR(100)` | YES | None | NULL | Authentication remember token |
| `created_at` | `TIMESTAMP` | YES | None | None | Account creation timestamp |
| `updated_at` | `TIMESTAMP` | YES | None | None | Last account update timestamp |

To bootstrap the first administrator, set `INITIAL_ADMIN_PHONE` and a strong `INITIAL_ADMIN_PASSWORD` in the environment, optionally set the `INITIAL_ADMIN_*` employee fields, and run `php artisan db:seed`. The account must change this password at first login. Remove the bootstrap password from the environment after seeding. The seeder will not promote an existing non-admin account.

The employee-table migration preserves employee IDs and normalizes legacy login phone numbers by removing whitespace, parentheses, and hyphens. It stops before changing the schema if a non-null phone number is invalid or if two numbers normalize to the same value; correct the affected employee records and rerun the migration.

### 2.1.1 Entity Set: `EMPLOYEES`
Employment and permissions record. Existing lead, team, and roster records continue to reference this table.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Employee ID used by business records |
| `user_id` | `BIGINT UNSIGNED` | YES | **FK, UK** $\rightarrow$ `users.id` | NULL | Login account; unique one-to-one link |
| `office_id` | `VARCHAR(50)` | YES | **UK** | NULL for legacy rows | Official office employee ID |
| `name` | `VARCHAR(255)` | NO | None | None | Employee name |
| `designation` | `VARCHAR(100)` | YES | None | NULL | Job title; distinct from system role |
| `joining_date` | `DATE` | YES | None | NULL for legacy rows | Official joining date |
| `role` | `ENUM` | NO | None | `'Sales Executive'` | `Admin`, `Supervisor`, `Team Leader`, `Sales Executive`, `Accounts`, `MarketingExecutive` |
| `status` | `ENUM` | NO | None | `'Active'` | `Active`, `Inactive`, `Suspended` |
| `shift_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `shifts.id` | NULL | Default shift |
| `created_at` | `TIMESTAMP` | YES | None | None | Employee record creation time |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Last employment record update |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Employee who last edited the record |

### 2.1.2 Entity Set: `EMPLOYEE_DETAILS`
Personal profile data. The NID value is application-encrypted; it is not searchable or unique-indexed.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Profile row ID |
| `employee_id` | `BIGINT UNSIGNED` | NO | **FK, UK** $\rightarrow$ `employees.id` | None | Unique employee profile |
| `email` | `VARCHAR(255)` | YES | **UK** | NULL | Optional personal email |
| `secondary_contact_number` | `VARCHAR(25)` | YES | None | NULL | Alternative contact number |
| `emergency_contact_name` | `VARCHAR(150)` | YES | None | NULL | Emergency contact name |
| `emergency_contact_number` | `VARCHAR(25)` | YES | None | NULL | Emergency contact phone |
| `dob` | `DATE` | YES | None | NULL | Date of birth |
| `nid_number` | `TEXT` | YES | None | NULL | Encrypted National ID |
| `blood_group` | `VARCHAR(10)` | YES | None | NULL | Blood group |
| `present_address` | `TEXT` | YES | None | NULL | Present address |
| `permanent_address` | `TEXT` | YES | None | NULL | Permanent address |
| `created_at`, `updated_at` | `TIMESTAMP` | YES | None | None | Profile timestamps |

Onboarding is complete when `dob`, `emergency_contact_name`, `emergency_contact_number`, `blood_group`, `present_address`, and `permanent_address` are populated. Email, secondary contact, and NID are optional.

**Onboarding sequence:** An Admin creates the user and essential employee record with a temporary password. The first successful login forces a password change, then requires completion of the profile fields above before the dashboard is available. Employees may directly update personal details. Changes to name, office ID, designation, joining date, role, employment status, or login phone remain pending until an authorized higher role approves them. Admin edits to any employee's essential information take effect directly and supersede that employee's pending requests.

### 2.1.3 Entity Set: `EMPLOYEE_CHANGE_REQUESTS`
Audit record for employee-requested changes to essential employment and account data.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Change request ID |
| `employee_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | Employee whose record is changing |
| `requested_by_employee_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | Requesting employee |
| `requested_changes` | `JSON` | NO | None | None | Proposed name, office ID, designation, joining date, role, status, and/or login phone |
| `reason` | `TEXT` | YES | None | NULL | Employee-provided reason |
| `status` | `ENUM` | NO | **INDEX** | `'Pending'` | `Pending`, `Approved`, `Rejected` |
| `reviewed_by_employee_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Higher-role reviewer |
| `review_note` | `TEXT` | YES | None | NULL | Reviewer note |
| `reviewed_at` | `TIMESTAMP` | YES | None | NULL | Decision timestamp |
| `created_at`, `updated_at` | `TIMESTAMP` | YES | None | None | Request timestamps |

**Approval policy:** `Admin > Supervisor > Team Leader > Sales Executive / Accounts / MarketingExecutive`. Any employee whose role is above the requester may approve or reject; self-approval is forbidden. Admin edits take effect directly. Accounts and MarketingExecutive share the Sales Executive approval rank.

**MarketingExecutive responsibilities from the interim Apps Script:** import leads in bulk; add or reactivate individual leads; create campaigns and associate lead submissions with campaign/source/course data; and route new leads through automatic distribution or the unassigned queue. The current Laravel ERP has no campaign-creation or lead-import routes yet; adding this role makes it assignable, but does not grant those not-yet-built capabilities.

---

### 2.2 Entity Set: `TEAMS`
Operational sales squads assigned to a specific Team Leader.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Unique Team ID |
| `name` | `VARCHAR(100)` | NO | None | None | Team designation (e.g. "Alpha", "Titan") |
| `team_leader_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Current assigned Team Leader |
| `is_active` | `BOOLEAN` | NO | None | `TRUE` | Whether team receives lead batches |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Team creation timestamp |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Last modification timestamp |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Admin who modified team attributes |

---

### 2.3 Entity Set: `SUPERVISOR_TEAMS`
Junction table mapping the **One-to-Many (1:N)** supervision structure from a Supervisor to multiple Teams.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Unique Mapping ID |
| `supervisor_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | User ID with role `'Supervisor'` |
| `team_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `teams.id` | None | Team supervised by this supervisor |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Timestamp when team was assigned |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Last modification timestamp |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Admin who changed supervision mapping |

> **Table Constraints:** `UNIQUE KEY unique_supervisor_team (supervisor_id, team_id)`

---

### 2.4 Entity Set: `COURSES`
EdTech course catalog, curriculum specifications, and admission criteria.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Unique Course ID |
| `title` | `VARCHAR(150)` | NO | None | None | Course name (e.g., "Fullstack Web Development") |
| `code` | `VARCHAR(50)` | NO | **UK** | None | Unique course code (e.g., `FSWD-101`) |
| `description` | `TEXT` | YES | None | NULL | Comprehensive course descriptions & syllabus |
| `conditions` | `TEXT` | YES | None | NULL | Admission conditions, prerequisite skills, and terms |
| `subject_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `subjects.id` | NULL | Course subject; existing values are migrated from the former enum |
| `course_topic_id` | `BIGINT UNSIGNED` | YES | **Composite FK** with `subject_id` $\rightarrow$ `course_topics.(subject_id, id)` | NULL | Curriculum topic; must belong to the selected subject |
| `version` | `ENUM` | NO | None | `'Bangla'` | Medium of instruction: `'Bangla'`, `'English'` |
| `category` | `VARCHAR(100)` | YES | None | NULL | Broader departmental category |
| `base_fee` | `DECIMAL(10,2)` | NO | None | None | Standard regular course fee in BDT |
| `is_active` | `BOOLEAN` | NO | None | `TRUE` | Whether currently enrolling |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Creation timestamp |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Last modification timestamp |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | User who modified course details |

### 2.4.1 Entity Set: `SUBJECTS`
Admin-managed top-level course subjects. Existing course enum values are copied into this catalogue during migration.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Subject ID |
| `name` | `VARCHAR(100)` | NO | **UK** | None | Unique subject name, e.g. English or Mathematics |
| `created_at` | `TIMESTAMP` | NO | None | Current timestamp | Creation timestamp |
| `updated_at` | `TIMESTAMP` | NO | None | Current timestamp | Last update timestamp |

### 2.4.2 Entity Set: `COURSE_TOPICS`
Topics form the second level of the course catalogue. Topic names are unique within each subject; a composite key prevents a course from pairing a topic with a different subject.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Topic ID |
| `subject_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `subjects.id` | None | Parent subject |
| `name` | `VARCHAR(100)` | NO | **UK** with `subject_id` | None | Topic, e.g. Phonics, Mind Math, or Oral Math |
| `created_at` | `TIMESTAMP` | NO | None | Current timestamp | Creation timestamp |
| `updated_at` | `TIMESTAMP` | NO | None | Current timestamp | Last update timestamp |

> **Table Constraints:** `UNIQUE (subject_id, name)` and `UNIQUE (subject_id, id)`; courses reference `(subject_id, course_topic_id)` to enforce subject/topic consistency.

### 2.4.3 Entity Set: `COURSE_BATCH`
Course-specific cohorts. Batch numbers and short codes are unique within a course. Admitted student count and vacancy are calculated from enrollments rather than stored.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Batch ID |
| `course_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `courses.id` | None | Course offered by this batch |
| `batch_number` | `INT UNSIGNED` | NO | **UK** with `course_id` | None | Batch sequence for the course |
| `batch_name` | `VARCHAR(100)` | NO | None | None | Display name of the batch |
| `short_code` | `VARCHAR(30)` | NO | **UK** with `course_id` | None | Course-specific batch code |
| `capacity` | `INT UNSIGNED` | NO | None | None | Maximum intended student capacity |
| `is_latest` | `BOOLEAN` | NO | None | `FALSE` | Whether this is the course's latest batch |
| `start_date` | `DATE` | NO | None | None | Batch start date |
| `created_at` | `TIMESTAMP` | NO | None | Current timestamp | Creation timestamp |
| `updated_at` | `TIMESTAMP` | NO | None | Current timestamp | Last update timestamp |

> **Calculated values:** `students_admitted` counts assigned enrollments whose status is not `Cancelled`. Conversion creates a `Pending Payment` enrollment and reserves a seat immediately; cancelling the enrollment releases it. `vacancy` is `max(capacity - students_admitted, 0)`.

---

### 2.5 Entity Set: `LEADS_SOURCE`
Marketing and acquisition channels originating student leads.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `source_id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Unique Lead Source ID |
| `channel` | `ENUM` | NO | None | None | Values: `'fb'`, `'wp'`, `'googleads'`, `'gptads'`, `'googleform'`, `'reference'`, `'website'`, `'phone'`, `'event'`, `'manual'`, `'other'` |
| `campaign_name` | `VARCHAR(150)` | YES | None | NULL | Ad Campaign / Adset / Event Title |
| `course_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `courses.id` | NULL | One course linked to this campaign; multiple campaigns may link to the same course. Required for campaigns created in the application; nullable for existing/unattributed source records. |
| `distribution_mode` | `ENUM` | NO | None | `'Auto'` | Campaign lead distribution mode: `'Auto'` or `'Manual'` |
| `is_active` | `BOOLEAN` | NO | None | `TRUE` | Whether the campaign is active |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Source creation timestamp |
| `created_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Employee who created the campaign |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Modification timestamp |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Employee who last edited the campaign |

---

### 2.6 Entity Set: `LEADS`
Central repository for customer inquiries, demographic context, and active ownership.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Unique Lead ID |
| `source_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `leads_source.source_id` | None | Originating acquisition source channel |
| `student_name` | `VARCHAR(150)` | NO | None | None | Student / Inquirer full name |
| `guardian_name` | `VARCHAR(150)` | YES | None | NULL | Parent or guardian name |
| `contact_number` | `VARCHAR(25)` | NO | **INDEX** | None | Primary mobile phone number |
| `whatsapp_number` | `VARCHAR(25)` | YES | None | NULL | WhatsApp contact number, when different from the primary number |
| `alternative_contact_number`| `VARCHAR(25)` | YES | **INDEX** | NULL | Secondary / Guardian contact number |
| `email` | `VARCHAR(120)` | YES | None | NULL | Inquirer email address |
| `age` | `INT UNSIGNED` | YES | None | NULL | Age of the student |
| `class` | `VARCHAR(50)` | YES | None | NULL | Academic grade / Educational background |
| `interested_course_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `courses.id` | NULL | Course inquired about |
| `status` | `ENUM` | NO | **INDEX** | `'New'` | Values: `'New'`, `'Assigned'`, `'Contacted'`, `'Follow-up'`, `'Interested'`, `'Enrolled - Pending Payment'`, `'Payment Under Review'`, `'Converted'`, `'Not Interested'`, `'Unreachable'`, `'Lost'` |
| `current_owner_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id`, **INDEX** | NULL | Sales Executive actively working this lead |
| `team_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `teams.id` | NULL | Team currently owning this lead |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Lead ingestion timestamp |
| `edited_at` | `TIMESTAMP` | YES | None | NULL | Last modification timestamp |
| `edited_by` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | User who updated lead details |

---

### 2.7 Entity Set: `LEAD_TRANSFERS`
Dual-approval cross-team transfer requests with Admin override and 7-day fallback automation.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Transfer ID |
| `lead_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `leads.id` | None | Lead to be transferred |
| `from_employee_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | Current Owner (Sender) |
| `to_employee_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | Target Owner (Receiver) |
| `from_team_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `teams.id` | None | Source Team |
| `to_team_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `teams.id` | None | Target Team |
| `reason` | `TEXT` | NO | None | None | Mandatory explanation for transfer |
| `sender_tl_employee_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Sender's Team Leader User ID |
| `sender_tl_approved_at`| `TIMESTAMP` | YES | None | NULL | Sender TL approval timestamp |
| `receiver_tl_employee_id` | `BIGINT UNSIGNED` | YES | **FK** $\rightarrow$ `employees.id` | NULL | Receiver's Team Leader User ID |
| `receiver_tl_approved_at`| `TIMESTAMP` | YES | None | NULL | Receiver TL approval timestamp |
| `is_auto_transferred` | `BOOLEAN` | NO | None | `FALSE` | TRUE if executed by 7-day auto-cron |
| `is_admin_override` | `BOOLEAN` | NO | None | `FALSE` | **TRUE if initiated by Admin (Instant bypass)** |
| `status` | `ENUM` | NO | None | `'Pending'` | Values: `'Pending'`, `'Approved'`, `'Rejected'` |
| `transferred_at` | `TIMESTAMP` | YES | None | NULL | Exact timestamp when transfer took effect |
| `created_at` | `TIMESTAMP` | NO | None | `CURRENT_TIMESTAMP` | Transfer request created timestamp |
| `updated_at` | `TIMESTAMP` | YES | None | NULL | Last modification timestamp |

> **Special Admin Rule:** If an **Admin** performs a lead transfer, `is_admin_override = TRUE`, `status = 'Approved'`, and `transferred_at = NOW()`. **No approvals from Team Leaders are required**; ownership transfers immediately!

---

### 2.8 Entity Set: `EMPLOYEE_ROSTERS`
Dynamic working schedules, roster duty status, and live availability state machine.

| Attribute Name | Data Type | Nullable | Key / Constraint | Default Value | Description |
| :--- | :--- | :---: | :---: | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | **PK** (Auto Inc) | Auto | Roster row ID |
| `employee_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `employees.id` | None | Employee on this roster date |
| `shift_id` | `BIGINT UNSIGNED` | NO | **FK** $\rightarrow$ `shifts.id` | None | Assigned shift |
| `roster_date` | `DATE` | NO | **UK** with `employee_id` | None | Scheduled date |
| `is_on_duty` | `BOOLEAN` | NO | None | `FALSE` | Whether employee is on duty |
| `availability_status` | `ENUM` | NO | None | `'Off Duty'` | `Available`, `Busy`, `Unavailable`, `Off Duty`, `Lazy`, `Resigned` |
| `last_active_at` | `TIMESTAMP` | YES | None | NULL | Last activity timestamp |
| `created_at`, `updated_at` | `TIMESTAMP` | YES | None | None | Roster timestamps |

---

### 2.9 Entity Set: `LEAD_BATCHES` & `LEAD_BATCH_ITEMS`
Batch allocation containers ($n=3$) distributed to sales executives.

| Entity | Attribute Name | Data Type | Key / Constraint | Description |
| :--- | :--- | :--- | :---: | :--- |
| `LEAD_BATCHES` | `id` | `BIGINT UNSIGNED` | **PK** | Batch ID |
| | `employee_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `employees.id` | Recipient Sales Executive |
| | `batch_size` | `INT UNSIGNED` | `default 3` | Assigned batch size ($n$) |
| | `status` | `ENUM` | `'Active'`, `'Completed'` | Active batch state |
| | `assigned_at` | `TIMESTAMP` | Default Now | Batch assignment time |
| | `completed_at` | `TIMESTAMP` | NULL | When all items finished |
| `LEAD_BATCH_ITEMS` | `id` | `BIGINT UNSIGNED` | **PK** | Batch Item ID |
| | `batch_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `lead_batches.id` | Parent Batch |
| | `lead_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `leads.id` | Lead assigned |
| | `is_completed` | `BOOLEAN` | `default FALSE` | Completed flag |
| | `action_taken` | `VARCHAR(50)` | NULL | Action outcome |
| | `completed_at` | `TIMESTAMP` | NULL | Completion timestamp |

---

### 2.10 Entity Sets: `LEAD_FEEDBACKS` & `LEAD_FOLLOWUPS`

| Entity | Attribute Name | Data Type | Key / Constraint | Description |
| :--- | :--- | :--- | :---: | :--- |
| `LEAD_FEEDBACKS` | `id` | `BIGINT UNSIGNED` | **PK** | Feedback Log ID |
| | `lead_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `leads.id` | Inquired Lead |
| | `employee_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `employees.id` | Executive submitting feedback |
| | `call_status` | `VARCHAR(50)` | NOT NULL | Connected, Busy, WA, etc. |
| | `customer_interest` | `VARCHAR(50)` | NOT NULL | Highly Interested, etc. |
| | `customer_concern` | `TEXT` | NULL | Objection / Questions |
| | `sales_note` | `TEXT` | NOT NULL | Detailed notes |
| | `next_action` | `VARCHAR(50)` | NOT NULL | Follow-up, Converted, etc. |
| | `action_date` | `TIMESTAMP` | NULL | Date and time the lead action occurred |
| | `created_at` | `TIMESTAMP` | Immutable | Activity timestamp |
| `LEAD_FOLLOWUPS` | `id` | `BIGINT UNSIGNED` | **PK** | Follow-up ID |
| | `lead_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `leads.id` | Associated Lead |
| | `employee_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `employees.id` | Assigned Executive |
| | `followup_date_time` | `DATETIME` | **INDEX** | Scheduled target date & time |
| | `note` | `TEXT` | NULL | Target discussion points |
| | `is_done` | `BOOLEAN` | `default FALSE` | Completion flag |
| | `overdue_alert_sent` | `BOOLEAN` | `default FALSE` | Alert trigger flag |

---

### 2.11 Entity Sets: `ENROLLMENTS`, `INSTALLMENT_SCHEDULES` & `PAYMENTS`

| Entity | Attribute Name | Data Type | Key / Constraint | Description |
| :--- | :--- | :--- | :---: | :--- |
| `ENROLLMENTS` | `id` | `BIGINT UNSIGNED` | **PK** | Enrollment Record ID |
| | `lead_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `leads.id`, **UK** | 1:1 with Converted Lead |
| | `course_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `courses.id` | Enrolled Course |
| | `course_batch_id` | `BIGINT UNSIGNED` | NULL; composite **FK** with `course_id` $\rightarrow$ `course_batch.(course_id, id)` | Optional batch; must belong to the enrolled course |
| | `sales_executive_id`| `BIGINT UNSIGNED`| **FK** $\rightarrow$ `employees.id` | Sales Rep |
| | `secure_uuid` | `VARCHAR(64)` | **UK** | Portal link `/enroll/verify/{uuid}` |
| | `student_name` | `VARCHAR(150)` | NOT NULL | Student Name |
| | `student_phone` | `VARCHAR(25)` | NOT NULL | Student Mobile |
| | `final_deal_amount` | `DECIMAL(10,2)` | NOT NULL | Net admission fee |
| | `total_installments`| `INT UNSIGNED` | `default 1` | Installment count |
| | `status` | `ENUM` | NOT NULL | `Pending Payment`, `Payment Under Review`, `Enrolled`, `Cancelled`; cancelled enrollments do not reserve a seat |
| `INSTALLMENT_SCHEDULES`| `id` | `BIGINT UNSIGNED` | **PK** | Schedule ID |
| | `enrollment_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `enrollments.id` | Parent Enrollment |
| | `installment_number`| `INT UNSIGNED` | NOT NULL | Sequence (1, 2, 3...) |
| | `due_amount` | `DECIMAL(10,2)` | NOT NULL | Tranche amount |
| | `due_date` | `DATE` | **INDEX** | Due date |
| | `followup_generated`| `BOOLEAN` | `default FALSE` | $T-2$ alert flag |
| | `status` | `ENUM` | NOT NULL | `Pending`, `Under Review`, `Paid`, `Overdue` |
| `PAYMENTS` | `id` | `BIGINT UNSIGNED` | **PK** | Payment ID |
| | `enrollment_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `enrollments.id` | Associated Enrollment |
| | `installment_schedule_id`| `BIGINT UNSIGNED`| **FK** $\rightarrow$ `installment_schedules.id`| Tranche |
| | `amount` | `DECIMAL(10,2)` | NOT NULL | Amount paid |
| | `payment_date` | `TIMESTAMP` | NULL | Date and time payment was made |
| | `payment_method` | `ENUM` | NOT NULL | `bKash`, `SSLCommerz`, `Manual Bank`, `Manual MFS` |
| | `transaction_id` | `VARCHAR(100)` | NULL | Gateway or Bank TrxID |
| | `slip_screenshot_path`| `VARCHAR(255)`| NULL | Image slip path |
| | `verified_by_employee_id`| `BIGINT UNSIGNED`| **FK** $\rightarrow$ `employees.id` | Accounts Officer |
| | `verified_date` | `TIMESTAMP` | NULL | Date and time payment was verified (renamed from `verified_at`) |
| | `status` | `ENUM` | NOT NULL | `Pending`, `Verified`, `Rejected` |

---

### 2.12 Entity Sets: `DUPLICATE_LEAD_REVIEWS` & `SYSTEM_SETTINGS`

| Entity | Attribute Name | Data Type | Key / Constraint | Description |
| :--- | :--- | :--- | :---: | :--- |
| `DUPLICATE_LEAD_REVIEWS`| `id` | `BIGINT UNSIGNED` | **PK** | Review Queue ID |
| | `incoming_lead_data`| `JSON` | NOT NULL | Incoming inquiry payload |
| | `matched_lead_id` | `BIGINT UNSIGNED` | **FK** $\rightarrow$ `leads.id` | Matched Lead |
| | `match_reason` | `VARCHAR(255)` | NOT NULL | Multi-course / Phone Match |
| | `status` | `ENUM` | NOT NULL | `Pending Review`, `Approved New`, `Merged`, `Discarded` |
| | `reviewed_by_employee_id`| `BIGINT UNSIGNED`| **FK** $\rightarrow$ `employees.id` | Admin/Supervisor |
| `SYSTEM_SETTINGS` | `id` | `BIGINT UNSIGNED` | **PK** | Setting ID |
| | `key` | `VARCHAR(100)` | **UK** | Configuration key |
| | `value` | `TEXT` | NOT NULL | Configuration value |

---

## 3. Foreign Key Reference Mapping & Cascade Policies

| Source Entity Table | Foreign Key Column | Target Entity Table | Target Column | On Delete Action | On Update Action |
| :--- | :--- | :--- | :--- | :---: | :---: |
| `EMPLOYEES` | `user_id` | `USERS` | `id` | `CASCADE` | `CASCADE` |
| `EMPLOYEE_DETAILS` | `employee_id` | `EMPLOYEES` | `id` | `CASCADE` | `CASCADE` |
| `EMPLOYEE_CHANGE_REQUESTS` | `employee_id`, `requested_by_employee_id` | `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `EMPLOYEE_CHANGE_REQUESTS` | `reviewed_by_employee_id` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `EMPLOYEES` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `TEAMS` | `team_leader_id` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `TEAMS` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `SUPERVISOR_TEAMS` | `supervisor_id` | `EMPLOYEES` | `id` | `CASCADE` | `CASCADE` |
| `SUPERVISOR_TEAMS` | `team_id` | `TEAMS` | `id` | `CASCADE` | `CASCADE` |
| `SUPERVISOR_TEAMS` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `EMPLOYEE_ROSTERS` | `employee_id` | `EMPLOYEES` | `id` | `CASCADE` | `CASCADE` |
| `EMPLOYEE_ROSTERS` | `shift_id` | `SHIFTS` | `id` | `RESTRICT` | `CASCADE` |
| `COURSES` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `LEADS_SOURCE` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `LEADS_SOURCE` | `course_id` | `COURSES` | `id` | `RESTRICT` | `CASCADE` |
| `LEADS` | `source_id` | `LEADS_SOURCE` | `source_id` | `RESTRICT` | `CASCADE` |
| `LEADS` | `interested_course_id`| `COURSES` | `id` | `SET NULL` | `CASCADE` |
| `LEADS` | `current_owner_id` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `LEADS` | `team_id` | `TEAMS` | `id` | `SET NULL` | `CASCADE` |
| `LEADS` | `edited_by` | `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |
| `LEAD_BATCHES` | `employee_id` | `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `LEAD_BATCH_ITEMS` | `batch_id` | `LEAD_BATCHES` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_BATCH_ITEMS` | `lead_id` | `LEADS` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_FEEDBACKS` | `lead_id` | `LEADS` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_FEEDBACKS` | `employee_id` | `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `LEAD_FOLLOWUPS` | `lead_id` | `LEADS` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_FOLLOWUPS` | `employee_id` | `EMPLOYEES` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_TRANSFERS` | `lead_id` | `LEADS` | `id` | `CASCADE` | `CASCADE` |
| `LEAD_TRANSFERS` | `from_employee_id` | `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `LEAD_TRANSFERS` | `to_employee_id` | `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `LEAD_TRANSFERS` | `from_team_id` | `TEAMS` | `id` | `RESTRICT` | `CASCADE` |
| `LEAD_TRANSFERS` | `to_team_id` | `TEAMS` | `id` | `RESTRICT` | `CASCADE` |
| `ENROLLMENTS` | `lead_id` | `LEADS` | `id` | `RESTRICT` | `CASCADE` |
| `ENROLLMENTS` | `course_id` | `COURSES` | `id` | `RESTRICT` | `CASCADE` |
| `COURSE_TOPICS` | `subject_id` | `SUBJECTS` | `id` | `CASCADE` | `CASCADE` |
| `COURSES` | `subject_id` | `SUBJECTS` | `id` | `RESTRICT` | `CASCADE` |
| `COURSES` | `subject_id`, `course_topic_id` | `COURSE_TOPICS` | `subject_id`, `id` | `RESTRICT` | `CASCADE` |
| `COURSE_BATCH` | `course_id` | `COURSES` | `id` | `RESTRICT` | `CASCADE` |
| `ENROLLMENTS` | `course_id`, `course_batch_id` | `COURSE_BATCH` | `course_id`, `id` | `RESTRICT` | `CASCADE` |
| `ENROLLMENTS` | `sales_executive_id`| `EMPLOYEES` | `id` | `RESTRICT` | `CASCADE` |
| `INSTALLMENT_SCHEDULES`| `enrollment_id` | `ENROLLMENTS` | `id` | `CASCADE` | `CASCADE` |
| `PAYMENTS` | `enrollment_id` | `ENROLLMENTS` | `id` | `RESTRICT` | `CASCADE` |
| `PAYMENTS` | `installment_schedule_id`| `INSTALLMENT_SCHEDULES`| `id`| `SET NULL` | `CASCADE` |
| `DUPLICATE_LEAD_REVIEWS`| `matched_lead_id` | `LEADS` | `id` | `CASCADE` | `CASCADE` |
| `DUPLICATE_LEAD_REVIEWS`| `reviewed_by_employee_id`| `EMPLOYEES` | `id` | `SET NULL` | `CASCADE` |

---

## 4. Database Triggers & Architectural Rationale

### 4.1 Architectural Decision: Triggers vs. Stored Procedures vs. Laravel Services
* **Stored Procedures (Avoided):** Not used. Business logic (such as dynamic batch scarcity calculation, lead distribution, and customer enrollment link creation) is encapsulated inside Laravel 13 Domain Services (`app/Modules/Lead/Services/`). This allows full Pest/PHPUnit testability, transactional rollbacks (`DB::transaction()`), asynchronous event dispatching, and queue processing.
* **Scheduled Operations:** 7-day transfer timeouts and $T-2$ installment notifications are driven by Laravel Scheduled Jobs (`php artisan schedule:run`) so they can dispatch push notifications and SMS alerts.
* **Defensive Triggers (Enforced):** MySQL Triggers are strictly employed as **defensive storage guards** to enforce data immutability and automatic state timestamps.

### 4.2 Immutability Triggers on `lead_feedbacks`
To strictly prevent tampering with historical call logs, notes, or outcomes, any `DELETE` or `UPDATE` operation attempted on `lead_feedbacks` is physically blocked at the database engine level:

```sql
DELIMITER //

-- Hard block on DELETE
CREATE TRIGGER `prevent_feedback_delete`
BEFORE DELETE ON `lead_feedbacks`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'CRITICAL ERROR: Lead Feedbacks are strictly immutable and cannot be deleted.';
END //

-- Hard block on UPDATE
CREATE TRIGGER `prevent_feedback_update`
BEFORE UPDATE ON `lead_feedbacks`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'CRITICAL ERROR: Lead Feedbacks are strictly immutable and cannot be edited.';
END //

DELIMITER ;
```

### 4.3 State Transition Trigger on `lead_transfers`
Automatically populates `transferred_at = CURRENT_TIMESTAMP()` the exact moment a transfer moves into `'Approved'` status:

```sql
DELIMITER //

CREATE TRIGGER `set_transfer_timestamp`
BEFORE UPDATE ON `lead_transfers`
FOR EACH ROW
BEGIN
    IF NEW.status = 'Approved' AND OLD.status != 'Approved' AND NEW.transferred_at IS NULL THEN
        SET NEW.transferred_at = CURRENT_TIMESTAMP();
    END IF;
END //

DELIMITER ;
```
