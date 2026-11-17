<?php

namespace App\Http\Middleware;

use App\Http\Controllers\DeveloperController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $isDeveloperView = $request->session()->has(DeveloperController::SESSION_KEY);

        if (! $isDeveloperView && $user->must_change_password && ! $request->routeIs('password.change.*', 'logout')) {
            return redirect()->route('password.change.edit');
        }

        $employee = $user->employee;

        if ($employee === null) {
            abort(403, 'This account is not linked to an employee record.');
        }

        if ($employee->status !== 'Active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['phone_number' => 'Your account is '.strtolower($employee->status).'. Contact an administrator.']);
        }

        if (! $isDeveloperView && ! $employee->isProfileComplete() && ! $request->routeIs('profile.*', 'password.change.*', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('status', 'Complete the required profile fields to continue.');
        }

        return $next($request);
    }
}
