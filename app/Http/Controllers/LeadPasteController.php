<?php

namespace App\Http\Controllers;

use App\Actions\ImportLeadsFromPaste;
use App\Models\LeadSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadPasteController extends Controller
{
    private const ROLES = ['Admin', 'MarketingExecutive'];

    public function create(Request $request): View
    {
        $this->employeeWithRole($request, self::ROLES);

        return view('leads.paste', [
            'channels' => LeadSource::query()->select('channel')->distinct()->orderBy('channel')->pluck('channel')
                ->mapWithKeys(fn (string $channel): array => [$channel => LeadSource::CHANNELS[$channel] ?? ucfirst($channel)]),
            'maxLines' => ImportLeadsFromPaste::MAX_LINES,
        ]);
    }

    public function store(Request $request, ImportLeadsFromPaste $import): RedirectResponse
    {
        $employee = $this->employeeWithRole($request, self::ROLES);
        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::exists('leads_source', 'channel')],
            'lines' => ['required', 'string', 'max:200000'],
        ], ['lines.required' => 'Paste at least one line.']);

        return redirect()->route('leads.paste')->with('paste_result', $import->handle($validated['lines'], $validated['channel'], $employee));
    }
}
