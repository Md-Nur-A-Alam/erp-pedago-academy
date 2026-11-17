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
