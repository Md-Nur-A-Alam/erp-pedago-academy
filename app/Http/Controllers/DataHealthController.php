<?php

namespace App\Http\Controllers;

use App\Actions\DataHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DataHealthController extends Controller
{
    public function index(Request $request, DataHealth $health): View
    {
        $this->employeeWithRole($request);

        return view('data-health.index', ['checks' => $health->checks()]);
    }

    public function fix(Request $request, DataHealth $health): RedirectResponse
    {
        $this->employeeWithRole($request);
        $validated = $request->validate(['fix' => ['required', Rule::in(['orphans', 'phones'])]]);

        $message = $validated['fix'] === 'orphans'
            ? $health->returnOrphansToQueue().' leads returned to the queue.'
            : $health->tidyPhones().' phone numbers tidied.';

        return redirect()->route('data-health.index')->with('status', $message);
    }
}
