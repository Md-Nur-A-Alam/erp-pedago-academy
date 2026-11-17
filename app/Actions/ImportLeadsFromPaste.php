<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Support\Facades\DB;

/**
 * Leads pasted straight from a spreadsheet, one per line:
 * name, phone, campaign, course, district, age, guardian name, remarks (the rest of the line).
 * Campaign and course are matched by name. A number already in the system is a repeat inquiry, not a new lead.
 */
class ImportLeadsFromPaste
{
    public const MAX_LINES = 500;

    public function __construct(private PhoneNumbers $phones, private HandleRepeatInquiry $repeat) {}

    /**
     * @return array{added: int, repeats: int, skipped: int, unmatched_campaigns: int, unmatched_courses: int, details: list<array{line: string, reason: string}>}
     */
    public function handle(string $text, string $channel, Employee $by): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), fn (string $line): bool => $line !== ''));
        $lines = array_slice($lines, 0, self::MAX_LINES);
        $result = ['added' => 0, 'repeats' => 0, 'skipped' => 0, 'unmatched_campaigns' => 0, 'unmatched_courses' => 0, 'details' => []];

        $courses = Course::query()->get(['id', 'title'])->keyBy(fn (Course $course): string => mb_strtolower(trim($course->title)));
        $campaigns = LeadSource::query()->whereNotNull('campaign_name')->get()->keyBy(fn (LeadSource $source): string => mb_strtolower(trim((string) $source->campaign_name)));

        DB::transaction(function () use ($lines, $channel, $by, $courses, $campaigns, &$result): void {
            $defaultSource = LeadSource::query()->firstOrCreate(['channel' => $channel, 'campaign_name' => null], ['created_by' => $by->id, 'edited_by' => $by->id]);

            foreach ($lines as $line) {
                $parts = array_map('trim', explode(',', $line));
                [$name, $rawPhone] = [$parts[0] ?? '', $parts[1] ?? ''];

                if ($name === '' || $rawPhone === '') {
                    $this->skip($result, $line, 'The name or the phone number is empty. This may be the header row.');

                    continue;
                }

                $phone = $this->phones->normalize($rawPhone);
                if ($phone === null) {
                    $this->skip($result, $line, 'The phone number is not valid. Use 01XXXXXXXXX, or + and the country code.');

                    continue;
                }

                $campaignName = $parts[2] ?? '';
                $source = $campaignName === '' ? $defaultSource : ($campaigns->get(mb_strtolower($campaignName)) ?? null);
                if ($campaignName !== '' && $source === null) {
                    $result['unmatched_campaigns']++;
                    $source = $defaultSource;
                }

                $courseName = $parts[3] ?? '';
                $course = $courseName === '' ? null : $courses->get(mb_strtolower($courseName));
                if ($courseName !== '' && $course === null) {
                    $result['unmatched_courses']++;
                }

                $existing = $this->phones->findLead($phone);

                if ($existing !== null) {
                    DuplicateLeadReview::query()->create([
                        'incoming_lead_data' => ['student_name' => $name, 'contact_number' => $phone, 'source_id' => $source->source_id],
                        'matched_lead_id' => $existing->id,
                        'match_reason' => 'Matching contact number',
                    ]);
                    $this->repeat->handle($existing, $source->source_id, $course?->id, $by);
                    $result['repeats']++;

                    continue;
                }

                $age = (int) preg_replace('/\D/', '', $parts[5] ?? '');

                Lead::query()->create([
                    'source_id' => $source->source_id,
                    'student_name' => $name,
                    'contact_number' => $phone,
                    'interested_course_id' => $course?->id,
                    'location' => ($parts[4] ?? '') ?: null,
                    'age' => $age >= 1 && $age <= 100 ? $age : null,
                    'guardian_name' => ($parts[6] ?? '') ?: null,
                    'remarks' => trim(implode(', ', array_slice($parts, 7))) ?: null,
                    'status' => 'New',
                ]);
                $result['added']++;
            }
        });

        return $result;
    }

    /**
     * @param  array{added: int, repeats: int, skipped: int, unmatched_campaigns: int, unmatched_courses: int, details: list<array{line: string, reason: string}>}  $result
     */
    private function skip(array &$result, string $line, string $reason): void
    {
        $result['skipped']++;

        if (count($result['details']) < 25) {
            $result['details'][] = ['line' => $line, 'reason' => $reason];
        }
    }
}
