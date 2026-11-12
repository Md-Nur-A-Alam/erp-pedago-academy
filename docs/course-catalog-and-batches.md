# Course catalogue and batches

## Subjects and topics

Courses use a two-level catalogue: each subject has multiple topics, and a course references both its subject and one topic. Administrators manage the catalogue from **Subjects** in the sidebar. A subject can contain topics such as English → Phonics or Mathematics → Mind Math, Oral Math, and Math Olympiad.

When creating or editing a course, choose a subject and one of its topics. Choosing **Other** for either field reveals a name field and creates that catalogue entry as part of saving the course. Existing course subjects are copied into the subject catalogue by the migration; existing courses have no topic until one is selected.

## Course batches

The `course_batch` table stores a course's batch number, name, short code, capacity, start date, and latest-batch flag. Batch numbers and short codes are unique within each course. Administrators can create and manage batches from **Course batches** in the sidebar.

Enrollments may optionally reference a batch using `course_batch_id`. The database also constrains that batch to belong to the same course as the enrollment. When a lead is converted, `CreateEnrollmentFromLead` creates its enrollment and assigns the lowest-numbered batch for that course with available seats in one transaction. A course-row lock serializes conversions for that course, so simultaneous conversions cannot claim the same last seat. The new `Pending Payment` enrollment reserves a seat immediately; every enrollment except `Cancelled` counts toward capacity, and cancellation releases its seat. **Admitted / reserved** is calculated from these non-cancelled enrollments, and **Vacancy** is `max(capacity - admitted, 0)`.

If every batch is full, conversion fails with a validation error and leaves the lead unchanged. An administrator must create another batch or increase existing capacity before conversion can succeed. The `is_latest` flag is for administration and does not affect allocation order.

The application currently has no lead-conversion HTTP endpoint. The transaction-safe action is ready for that workflow to call; it does not add a conversion screen or route.

## Campaign course association

Each named campaign has one `course_id` pointing to its course, and a course may have multiple campaigns. Campaign creation and editing require a course selection. The database column remains nullable so existing campaign records without a safe course mapping and non-campaign source records remain intact; administrators can associate legacy campaigns when editing them. Deleting a course referenced by campaigns is blocked.

## Database relationships

- `subjects` has many `course_topics` and courses.
- `course_topics` belongs to a subject and may be referenced by multiple courses.
- Courses reference `subjects` and `course_topics`.
- Courses have many campaigns; each campaign belongs to one course.
- `course_batch` belongs to a course and has many enrollments.
- Enrollments keep their existing course relationship and may optionally belong to a batch of that same course.
- A converted lead has one enrollment; its batch reservation is created with the enrollment and the lead's `Converted` status in the same transaction.

The migration preserves each existing course's subject while moving it to `subject_id`. Rolling the migration back restores that subject name as a string; topics and batch associations are new data and do not have equivalents in the old schema.
