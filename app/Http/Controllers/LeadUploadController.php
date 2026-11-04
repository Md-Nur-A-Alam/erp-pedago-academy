<?php

namespace App\Http\Controllers;

use App\Actions\ImportLeadsFromCsv;
use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadUploadController extends Controller
{
    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const CSV_COLUMN_LABELS = [
        'student_name' => ['label' => 'Student name', 'description' => 'Required'],
        'contact_number' => ['label' => 'Primary phone', 'description' => 'Required'],
        'guardian_name' => ['label' => 'Guardian name', 'description' => 'Parent or guardian'],
        'whatsapp_number' => ['label' => 'WhatsApp number', 'description' => 'Optional'],
        'alternative_contact_number' => ['label' => 'Alternative number', 'description' => 'Second contact'],
        'email' => ['label' => 'Email', 'description' => 'Optional'],
        'location' => ['label' => 'Location', 'description' => 'City or area'],
        'age' => ['label' => 'Age', 'description' => 'Optional'],
        'class' => ['label' => 'Class', 'description' => 'Optional'],
        'student_parent_type' => ['label' => 'Student / parent', 'description' => 'Contact type'],
        'student_background' => ['label' => 'Student background', 'description' => 'Notes or context'],
        'ad_set_id' => ['label' => 'Ad set ID', 'description' => 'Optional'],
    ];

    private const REQUIRED_CSV_HEADERS = ['student_name', 'contact_number'];

    public function index(Request $request): View
    {
        $this->authorizeLeadImport($request);

        $channels = LeadSource::query()
            ->select('channel')
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel')
            ->mapWithKeys(fn (string $channel): array => [
                $channel => LeadSource::CHANNELS[$channel] ?? ucfirst($channel),
            ]);
        $campaignsByChannel = LeadSource::query()
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '<>', '')
            ->select('channel', 'campaign_name')
            ->distinct()
            ->orderBy('campaign_name')
            ->get()
            ->groupBy('channel')
            ->map(fn ($campaigns) => $campaigns->pluck('campaign_name')->values())
            ->all();

        return view('leads.upload', [
            'channels' => $channels,
            'campaignsByChannel' => $campaignsByChannel,
            'selectedChannel' => $request->old('channel'),
            'columns' => array_map(
                static fn (string $header): array => [
                    'name' => $header,
                    ...self::CSV_COLUMN_LABELS[$header],
                    'required' => in_array($header, self::REQUIRED_CSV_HEADERS, true),
                ],
                ImportLeadsFromCsv::CSV_HEADERS,
            ),
            'mode' => $request->query('mode') === 'single' ? 'single' : 'bulk',
        ]);
    }

    public function store(Request $request, ImportLeadsFromCsv $importLeads): RedirectResponse
    {
        $employee = $this->authorizeLeadImport($request);

        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::exists('leads_source', 'channel')],
            'campaign_name' => [
                'nullable',
                'string',
                'max:150',
                Rule::exists('leads_source', 'campaign_name')
                    ->where(fn ($query) => $query->where('channel', $request->input('channel'))),
            ],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $importLeads->handle(
            $validated['file'],
            $validated['channel'],
            $validated['campaign_name'] ?? null,
            $employee->id,
        );

        return redirect()->route('leads.upload')
            ->with('import_result', $result);
    }

    public function storeSingle(Request $request): RedirectResponse
    {
        $employee = $this->authorizeLeadImport($request);
        foreach (['contact_number', 'whatsapp_number', 'alternative_contact_number'] as $phoneField) {
            if ($request->has($phoneField)) {
                $request->merge([
                    $phoneField => User::normalizePhoneNumber((string) $request->input($phoneField)),
                ]);
            }
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::exists('leads_source', 'channel')],
            'campaign_name' => [
                'nullable',
                'string',
                'max:150',
                Rule::exists('leads_source', 'campaign_name')
                    ->where(fn ($query) => $query->where('channel', $request->input('channel'))),
            ],
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

        $channel = $validated['channel'];
        $campaignName = $validated['campaign_name'] ?? null;
        unset($validated['channel'], $validated['campaign_name']);

        $isDuplicate = DB::transaction(function () use ($validated, $channel, $campaignName, $employee): bool {
            $source = LeadSource::query()->firstOrCreate(
                [
                    'channel' => $channel,
                    'campaign_name' => filled($campaignName) ? trim($campaignName) : null,
                ],
                [
                    'created_by' => $employee->id,
                    'edited_by' => $employee->id,
                ],
            );
            $lead = Lead::query()->where('contact_number', $validated['contact_number'])->first();

            if ($lead !== null) {
                DuplicateLeadReview::query()->create([
                    'incoming_lead_data' => [...$validated, 'source_id' => $source->source_id],
                    'matched_lead_id' => $lead->id,
                    'match_reason' => 'Matching contact number',
                ]);

                return true;
            }

            Lead::query()->create([...$validated, 'source_id' => $source->source_id]);

            return false;
        });

        return redirect()->route('leads.upload', ['mode' => 'single'])
            ->with('single_result', $isDuplicate
                ? 'A matching phone number was found. The lead was sent to duplicate review.'
                : 'Lead added successfully.');
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizeLeadImport($request);

        $validated = $request->validate([
            'columns' => ['sometimes', 'array', 'min:2'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(ImportLeadsFromCsv::CSV_HEADERS)],
        ]);
        $requestedColumns = $validated['columns'] ?? ImportLeadsFromCsv::CSV_HEADERS;
        $missingRequiredHeaders = array_diff(self::REQUIRED_CSV_HEADERS, $requestedColumns);

        if ($missingRequiredHeaders !== []) {
            throw ValidationException::withMessages([
                'columns' => 'Student name and primary phone must be included in every template.',
            ]);
        }

        $selectedColumns = array_values(array_filter(
            ImportLeadsFromCsv::CSV_HEADERS,
            static fn (string $header): bool => in_array($header, $requestedColumns, true),
        ));
        $exampleValues = [
            'student_name' => 'Sara Rahman',
            'contact_number' => '01711000000',
            'guardian_name' => 'Abdul Rahman',
            'whatsapp_number' => '01811000000',
            'alternative_contact_number' => '',
            'email' => 'sara@example.com',
            'location' => 'Dhaka',
            'age' => '17',
            'class' => 'Class 11',
            'student_parent_type' => 'Student',
            'student_background' => '',
            'ad_set_id' => '',
        ];

        return response()->streamDownload(static function () use ($selectedColumns, $exampleValues): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $selectedColumns);
            fputcsv($output, array_map(
                static fn (string $header): string => $exampleValues[$header],
                $selectedColumns,
            ));
            fclose($output);
        }, 'pedago-leads-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeLeadImport(Request $request): Employee
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && $user->employee instanceof Employee
                && in_array($user->employee->role, ['Admin', 'MarketingExecutive'], true),
            403,
        );

        return $user->employee;
    }
}
