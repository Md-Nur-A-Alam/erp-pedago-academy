<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'description'])]
class SystemSetting extends Model
{
    use HasFactory;

    public const CREATED_AT = null;

    /**
     * Every setting the application reads, with its default and validation range.
     *
     * @var array<string, array{default: string, type: 'integer'|'boolean', min?: int, max?: int, label: string, unit?: string, group: string, description: string}>
     */
    public const DEFINITIONS = [
        'distribution_engine_enabled' => [
            'default' => '0',
            'type' => 'boolean',
            'label' => 'Automatic distribution',
            'group' => 'Lead distribution',
            'description' => 'When on, the engine hands queue leads to available executives on every scheduled cycle.',
        ],
        'lead_batch_size' => [
            'default' => '3',
            'type' => 'integer',
            'min' => 1,
            'max' => 50,
            'label' => 'Default lead batch size',
            'unit' => 'leads',
            'group' => 'Lead distribution',
            'description' => 'Default number of leads assigned in a batch.',
        ],
        'assignment_cutoff_minutes' => [
            'default' => '30',
            'type' => 'integer',
            'min' => 0,
            'max' => 180,
            'label' => 'Assignment cutoff',
            'unit' => 'min',
            'group' => 'Lead distribution',
            'description' => 'Minutes before shift end when new lead assignment stops.',
        ],
        'hot_lead_minutes' => [
            'default' => '60',
            'type' => 'integer',
            'min' => 5,
            'max' => 240,
            'label' => 'Hot lead window',
            'unit' => 'min',
            'group' => 'Lead priority',
            'description' => 'A lead received within this many minutes is Hot and is distributed and called before anything else.',
        ],
        'warm_lead_hours' => [
            'default' => '24',
            'type' => 'integer',
            'min' => 1,
            'max' => 72,
            'label' => 'Warm lead window',
            'unit' => 'hours',
            'group' => 'Lead priority',
            'description' => 'After the hot window a lead stays Warm for this many hours. Fresh (hot and warm) leads take three of every four slots in a cycle.',
        ],
        'cold_lead_hours' => [
            'default' => '72',
            'type' => 'integer',
            'min' => 24,
            'max' => 720,
            'label' => 'Cold lead threshold',
            'unit' => 'hours',
            'group' => 'Lead priority',
            'description' => 'A lead older than this without a first call is Cold. Between warm and cold it is Cool.',
        ],
        'whatsapp_thanks_message' => [
            'default' => 'আসসালামু আলাইকুম। আমি Pedago Academy থেকে {সেলস}। আজ সময় দিয়ে কথা বলার জন্য আপনাকে আন্তরিক ধন্যবাদ। {নাম}-এর বিষয়ে কোনো প্রশ্ন থাকলে এই নম্বরে যেকোনো সময় জানাবেন।',
            'type' => 'text',
            'min' => 0,
            'max' => 500,
            'label' => 'WhatsApp thank-you message',
            'unit' => '',
            'group' => 'Messages',
            'description' => 'Sent from the Thank on WhatsApp button after a call. Use {নাম} or {name} for the student, {অভিভাবক} or {guardian} for the guardian, and {সেলস} or {sales} for the sales person.',
        ],
        'max_sales_discount_percent' => [
            'default' => '20',
            'type' => 'integer',
            'min' => 0,
            'max' => 100,
            'label' => 'Largest discount a sales person can give',
            'unit' => '%',
            'group' => 'Enrollment',
            'description' => 'When a sales person enrolls a student, the discount on the course fee cannot be higher than this.',
        ],
        'max_installments' => [
            'default' => '6',
            'type' => 'integer',
            'min' => 1,
            'max' => 24,
            'label' => 'Most installments in a payment plan',
            'unit' => 'installments',
            'group' => 'Enrollment',
            'description' => 'The largest number of installments a student can be given. Installments fall due 30 days apart.',
        ],
        'followup_notice_hours' => [
            'default' => '30',
            'type' => 'integer',
            'min' => 1,
            'max' => 168,
            'label' => 'Follow-up reminder window',
            'unit' => 'hours',
            'group' => 'Follow-ups',
            'description' => 'A follow-up is "coming up" from this many hours before its time until the exact time. The owner gets a reminder when the window opens. At the exact time it becomes overdue.',
        ],
        'followup_overdue_repeat_hours' => [
            'default' => '6',
            'type' => 'integer',
            'min' => 1,
            'max' => 72,
            'label' => 'Overdue follow-up reminder every',
            'unit' => 'hours',
            'group' => 'Follow-ups',
            'description' => 'An overdue follow-up is announced at its exact time, then again this often until the sales person logs a new follow-up, a conversion or another outcome.',
        ],
        'leave_casual_days' => [
            'default' => '10',
            'type' => 'integer',
            'min' => 0,
            'max' => 60,
            'label' => 'Casual leave a year',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Paid casual leave for each employee in a calendar year. The Bangladesh Labour Act 2006, section 115, gives 10 days.',
        ],
        'leave_sick_days' => [
            'default' => '14',
            'type' => 'integer',
            'min' => 0,
            'max' => 60,
            'label' => 'Sick leave a year',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Paid sick leave in a calendar year. Section 116 gives 14 days in shops and commercial establishments.',
        ],
        'leave_sick_certificate_days' => [
            'default' => '3',
            'type' => 'integer',
            'min' => 1,
            'max' => 30,
            'label' => 'Sick leave that needs a certificate',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Sick leave longer than this needs the employee to confirm they hold a registered doctor’s certificate.',
        ],
        'leave_earned_work_days' => [
            'default' => '18',
            'type' => 'integer',
            'min' => 10,
            'max' => 60,
            'label' => 'Days worked for 1 day of annual leave',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Annual (earned) leave grows by 1 day for every this many days worked. Section 117 gives 1 day for every 18 days worked. It can be used after one full year of service.',
        ],
        'leave_earned_max_days' => [
            'default' => '40',
            'type' => 'integer',
            'min' => 0,
            'max' => 120,
            'label' => 'Most annual leave that can be held',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Annual leave cannot pile up beyond this many days.',
        ],
        'leave_maternity_days' => [
            'default' => '112',
            'type' => 'integer',
            'min' => 0,
            'max' => 240,
            'label' => 'Maternity leave',
            'unit' => 'days',
            'group' => 'Leave',
            'description' => 'Paid maternity leave for one confirmed pregnancy: 16 weeks, 8 before and 8 after the birth (section 46). Needs 6 months of service.',
        ],
        'kpi_calls_per_day' => [
            'default' => '25',
            'type' => 'integer',
            'min' => 1,
            'max' => 200,
            'label' => 'Calls per working day',
            'unit' => 'calls',
            'group' => 'Performance targets',
            'description' => 'Target number of logged calls for each sales person on a working day (Fridays are left out).',
        ],
        'kpi_first_call_minutes' => [
            'default' => '30',
            'type' => 'integer',
            'min' => 1,
            'max' => 1440,
            'label' => 'First-call time',
            'unit' => 'min',
            'group' => 'Performance targets',
            'description' => 'Target for the median time between a lead being assigned and the first logged call. Lower is better.',
        ],
        'kpi_followup_on_time_percent' => [
            'default' => '90',
            'type' => 'integer',
            'min' => 1,
            'max' => 100,
            'label' => 'Follow-ups kept on time',
            'unit' => '%',
            'group' => 'Performance targets',
            'description' => 'Target share of follow-ups that are done within an hour of the promised time.',
        ],
        'kpi_conversion_percent' => [
            'default' => '10',
            'type' => 'integer',
            'min' => 1,
            'max' => 100,
            'label' => 'Conversion rate',
            'unit' => '%',
            'group' => 'Performance targets',
            'description' => 'Target share of assigned leads that become students.',
        ],
        'lazy_threshold_hours' => [
            'default' => '24',
            'type' => 'integer',
            'min' => 1,
            'max' => 168,
            'label' => 'Lazy threshold',
            'unit' => 'hours',
            'group' => 'Follow-up discipline',
            'description' => 'Hours a follow-up may stay overdue before its executive is flagged Lazy.',
        ],
        'transfer_auto_approve_days' => [
            'default' => '7',
            'type' => 'integer',
            'min' => 1,
            'max' => 30,
            'label' => 'Transfer auto-approval',
            'unit' => 'days',
            'group' => 'Follow-up discipline',
            'description' => 'Days a pending transfer waits before it is approved automatically.',
        ],
        'installment_reminder_days' => [
            'default' => '2',
            'type' => 'integer',
            'min' => 0,
            'max' => 14,
            'label' => 'Installment reminder',
            'unit' => 'days',
            'group' => 'Enrollment and finance',
            'description' => 'Days before an installment is due when it is listed as due soon.',
        ],
    ];

    private const CACHE_BINDING = 'system-settings.values';

    protected static function booted(): void
    {
        static::saved(fn () => app()->forgetInstance(self::CACHE_BINDING));
        static::deleted(fn () => app()->forgetInstance(self::CACHE_BINDING));
    }

    /**
     * Read a setting without creating it, falling back to its defined default.
     */
    public static function valueOf(string $key): string
    {
        if (! app()->bound(self::CACHE_BINDING)) {
            app()->instance(self::CACHE_BINDING, static::query()->pluck('value', 'key')->all());
        }

        /** @var array<string, string> $values */
        $values = app(self::CACHE_BINDING);

        return (string) ($values[$key] ?? self::DEFINITIONS[$key]['default'] ?? '');
    }

    public static function integer(string $key): int
    {
        return (int) self::valueOf($key);
    }

    public static function enabled(string $key): bool
    {
        return self::valueOf($key) === '1';
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => self::DEFINITIONS[$key]['description'] ?? null],
        );
    }
}
