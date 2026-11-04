<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone_number' => User::normalizePhoneNumber((string) $request->input('phone_number', '')),
        ]);

        $credentials = $request->validate([
            'phone_number' => ['required', 'string', 'max:25', new PhoneNumber],
            'password' => ['required', 'string', 'min:6', 'max:12'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['phone_number' => 'The phone number or password is incorrect.'])
                ->onlyInput('phone_number');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User || $user->employee?->status !== 'Active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['phone_number' => 'This account is not active.'])
                ->onlyInput('phone_number');
        }

        if ($user->must_change_password) {
            return redirect()->route('password.change.edit');
        }

        if (! $user->employee->isProfileComplete()) {
            return redirect()->route('profile.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
