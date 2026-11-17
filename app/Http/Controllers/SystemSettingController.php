<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(Request $request): View
    {
        $this->employeeWithRole($request);
        $settings = [];

        foreach (SystemSetting::DEFINITIONS as $key => $definition) {
            $settings[$key] = SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $definition['default'], 'description' => $definition['description']],
            );
        }

        return view('settings.index', [
            'settings' => $settings,
            'groups' => collect(SystemSetting::DEFINITIONS)->groupBy('group', preserveKeys: true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->employeeWithRole($request);

        $rules = ['settings' => ['required', 'array:'.implode(',', array_keys(SystemSetting::DEFINITIONS))]];
        foreach (SystemSetting::DEFINITIONS as $key => $definition) {
            $rules["settings.{$key}"] = $definition['type'] === 'boolean'
                ? ['sometimes', 'required', 'in:0,1']
                : ['sometimes', 'required', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']];
        }
        $attributes = collect(SystemSetting::DEFINITIONS)
            ->mapWithKeys(fn (array $definition, string $key): array => ["settings.{$key}" => strtolower($definition['label'])])
            ->all();
        $validated = $request->validate($rules, [], $attributes);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['settings'] as $key => $value) {
                SystemSetting::put($key, (string) $value);
            }
        });

        return redirect()->route('settings.index')->with('status', 'System settings saved successfully.');
    }

    /**
     * Turn automatic distribution on or off from the top bar.
     */
    public function toggleEngine(Request $request): RedirectResponse
    {
        $this->employeeWithRole($request);
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $validated['enabled'];

        SystemSetting::put('distribution_engine_enabled', $enabled ? '1' : '0');

        return back()->with('status', $enabled
            ? 'Automatic distribution is on. Queue leads are assigned on every scheduled cycle.'
            : 'Automatic distribution is off. Leads stay in the queue until you run a cycle or assign them yourself.');
    }
}
