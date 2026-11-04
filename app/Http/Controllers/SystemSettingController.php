<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    /**
     * @var array<string, array{default: string, description: string}>
     */
    private const SETTING_DEFINITIONS = [
        'lead_batch_size' => [
            'default' => '3',
            'description' => 'Default number of leads assigned in a batch.',
        ],
        'assignment_cutoff_minutes' => [
            'default' => '30',
            'description' => 'Minutes before shift end when new lead assignment stops.',
        ],
    ];

    public function index(Request $request): View
    {
        $this->adminFor($request);
        $settings = [];

        foreach (self::SETTING_DEFINITIONS as $key => $definition) {
            $settings[$key] = SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $definition['default'], 'description' => $definition['description']],
            );
        }

        return view('settings.index', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->adminFor($request);
        $validated = $request->validate([
            'settings' => ['required', 'array:lead_batch_size,assignment_cutoff_minutes'],
            'settings.lead_batch_size' => ['required', 'integer', 'min:1', 'max:50'],
            'settings.assignment_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['settings'] as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => (string) $value,
                        'description' => self::SETTING_DEFINITIONS[$key]['description'],
                    ],
                );
            }
        });

        return redirect()->route('settings.index')->with('status', 'System settings saved successfully.');
    }

    private function adminFor(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;

        abort_unless($employee instanceof Employee && $employee->isAdmin(), 403);

        return $employee;
    }
}
