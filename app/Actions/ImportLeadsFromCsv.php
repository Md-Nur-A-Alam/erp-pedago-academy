<?php

namespace App\Actions;

use App\Models\DuplicateLeadReview;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportLeadsFromCsv
{
    private const MAX_ROWS = 500;

    public const CSV_HEADERS = [
        'student_name',
        'contact_number',
        'guardian_name',
        'whatsapp_number',
        'alternative_contact_number',
        'email',
        'location',
        'age',
        'class',
        'student_parent_type',
        'student_background',
        'ad_set_id',
    ];

    /**
     * @return array{total: int, imported: int, duplicates: int}
     */
    public function handle(UploadedFile $file, string $channel, ?string $campaignName, int $createdByEmployeeId): array
    {
        $rows = $this->readRows($file);

        return DB::transaction(function () use ($rows, $channel, $campaignName, $createdByEmployeeId): array {
            $source = LeadSource::query()->firstOrCreate(
                [
                    'channel' => $channel,
                    'campaign_name' => filled($campaignName) ? trim($campaignName) : null,
                ],
                [
                    'created_by' => $createdByEmployeeId,
                    'edited_by' => $createdByEmployeeId,
                ],
            );
            $contactNumbers = array_values(array_unique(array_column($rows, 'contact_number')));
            $knownLeads = Lead::query()
                ->whereIn('contact_number', $contactNumbers)
                ->orderBy('id')
                ->get()
                ->keyBy('contact_number');

            $imported = 0;
            $duplicates = 0;

            foreach ($rows as $row) {
                $row['source_id'] = $source->source_id;

                if ($knownLeads->has($row['contact_number'])) {
                    DuplicateLeadReview::query()->create([
                        'incoming_lead_data' => $row,
                        'matched_lead_id' => $knownLeads->get($row['contact_number'])->id,
                        'match_reason' => 'Matching contact number',
                    ]);
                    $duplicates++;

                    continue;
                }

                $lead = Lead::query()->create($row);
                $knownLeads->put($lead->contact_number, $lead);
                $imported++;
            }

            return [
                'total' => count($rows),
                'imported' => $imported,
                'duplicates' => $duplicates,
            ];
        });
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function readRows(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded CSV file could not be read.',
            ]);
        }

        try {
            $headers = fgetcsv($stream, null, ',', '"', '');

            if ($headers === false) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty.',
                ]);
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);
            $unknownHeaders = array_diff($headers, self::CSV_HEADERS);
            $missingHeaders = array_diff(['student_name', 'contact_number'], $headers);

            if (count($headers) !== count(array_unique($headers))) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file contains duplicate column headings.',
                ]);
            }

            if ($unknownHeaders !== [] || $missingHeaders !== []) {
                $headingIssues = [];

                if ($missingHeaders !== []) {
                    $headingIssues[] = 'Missing required headings: '.implode(', ', $missingHeaders).'.';
                }

                if ($unknownHeaders !== []) {
                    $headingIssues[] = 'Unsupported headings: '.implode(', ', $unknownHeaders).'.';
                }

                throw ValidationException::withMessages([
                    'file' => 'Use the template headings. '.implode(' ', $headingIssues),
                ]);
            }

            $rows = [];
            $errors = [];
            $lineNumber = 1;
            $rowCount = 0;

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $lineNumber++;

                if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $rowCount++;

                if ($rowCount > self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'file' => 'A CSV upload can contain at most 500 non-empty lead rows.',
                    ]);
                }

                if (count($values) !== count($headers)) {
                    $errors["rows.{$lineNumber}"] = "Row {$lineNumber} has a different number of values than the header.";

                    continue;
                }

                $row = array_combine($headers, array_map(
                    fn ($value): ?string => trim((string) $value) === '' ? null : trim((string) $value),
                    $values,
                ));
                $row = array_merge(array_fill_keys(self::CSV_HEADERS, null), $row);
                foreach (['contact_number', 'whatsapp_number', 'alternative_contact_number'] as $phoneField) {
                    if (filled($row[$phoneField])) {
                        $phoneNumber = Str::of((string) $row[$phoneField])
                            ->replaceMatches('/^p\s*:\s*/i', '')
                            ->toString();

                        $row[$phoneField] = User::normalizePhoneNumber($phoneNumber);
                    }
                }
                $row['age'] = $this->normalizeAge($row['age']);

                $validator = Validator::make($row, [
                    'student_name' => ['required', 'string', 'max:150'],
                    'contact_number' => ['required', 'string', 'max:25', new PhoneNumber],
                    'guardian_name' => ['nullable', 'string', 'max:150'],
                    'whatsapp_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
                    'alternative_contact_number' => ['nullable', 'string', 'max:25', new PhoneNumber],
                    'email' => ['nullable', 'email', 'max:120'],
                    'location' => ['nullable', 'string', 'max:255'],
                    'age' => ['nullable', 'integer', 'min:0', 'max:120'],
                    'class' => ['nullable', 'string', 'max:50'],
                    'student_parent_type' => ['nullable', 'string', 'max:30'],
                    'student_background' => ['nullable', 'string'],
                    'ad_set_id' => ['nullable', 'string', 'max:100'],
                ]);

                if ($validator->fails()) {
                    $errors["rows.{$lineNumber}"] = "Row {$lineNumber}: ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $rows[] = $validator->validated();
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file does not contain any lead rows.',
                ]);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function normalizeAge(?string $age): ?string
    {
        if ($age === null) {
            return null;
        }

        $age = strtr($age, [
            '০' => '0',
            '১' => '1',
            '২' => '2',
            '৩' => '3',
            '৪' => '4',
            '৫' => '5',
            '৬' => '6',
            '৭' => '7',
            '৮' => '8',
            '৯' => '9',
        ]);

        if (! preg_match('/^(\d+)(?:\.\d+)?\s*(?:years?|yrs?)?$/i', trim($age), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
