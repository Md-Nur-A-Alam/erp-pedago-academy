<?php

namespace App\Http\Controllers;

use App\Actions\StaffPasswordHasher;
use App\Models\Employee;
use App\Models\PasswordRevealLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffPasswordController extends Controller
{
    public function index(Request $request): View
    {
        $this->employeeWithRole($request);

        return $this->page($request);
    }

    /**
     * Show one employee's password once, after the admin confirms their own password and gives a reason.
     */
    public function reveal(Request $request, Employee $employee): View|RedirectResponse
    {
        $admin = $this->employeeWithRole($request);
        $validated = $this->confirm($request);
        $hasher = $this->hasher();
        $stored = (string) $employee->user?->password;

        if ($hasher === null || ! $hasher->isReadable($stored)) {
            return redirect()->route('staff-passwords.index')->withErrors([
                'reveal' => "{$employee->name}'s password cannot be read yet. It becomes readable the next time they sign in, or you can reset it now.",
            ]);
        }

        $this->log($request, $admin, $employee, 'Revealed', $validated['reason']);

        return $this->page($request, ['employee' => $employee, 'password' => $hasher->reveal($stored)]);
    }

    /**
     * Give an employee a new temporary password that they must change at their next sign-in.
     */
    public function reset(Request $request, Employee $employee): View
    {
        $admin = $this->employeeWithRole($request);
        $validated = $this->confirm($request);
        abort_if($employee->user === null, 404);

        $temporary = Str::password(10, symbols: false);
        $employee->user->update(['password' => $temporary, 'must_change_password' => true]);
        $this->log($request, $admin, $employee, 'Reset', $validated['reason']);

        return $this->page($request, ['employee' => $employee, 'password' => $temporary, 'temporary' => true]);
    }

    /**
     * @return array{current_password: string, reason: string}
     */
    private function confirm(Request $request): array
    {
        try {
            return $request->validate([
                'current_password' => ['required', 'current_password:web'],
                'reason' => ['required', 'string', 'min:5', 'max:255'],
            ], [
                'current_password.current_password' => 'Your own password is not correct.',
                'reason.required' => 'Say why you need this password. The reason is kept in the log.',
            ]);
        } catch (ValidationException $exception) {
            // The result page is shown at the POST address, so going "back" would try a GET there.
            throw $exception->redirectTo(route('staff-passwords.index'));
        }
    }

    private function log(Request $request, Employee $admin, Employee $target, string $action, string $reason): void
    {
        PasswordRevealLog::query()->create([
            'admin_employee_id' => $admin->id,
            'target_employee_id' => $target->id,
            'action' => $action,
            'reason' => $reason,
            'ip_address' => $request->ip(),
        ]);
    }

    private function hasher(): ?StaffPasswordHasher
    {
        $driver = Hash::driver();

        return $driver instanceof StaffPasswordHasher ? $driver : null;
    }

    /**
     * @param  array{employee: Employee, password: ?string, temporary?: bool}|null  $shown
     */
    private function page(Request $request, ?array $shown = null): View
    {
        $search = trim((string) $request->query('search', ''));
        $hasher = $this->hasher();

        $employees = Employee::query()
            ->with('user:id,phone_number,password,must_change_password')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('office_id', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $employees->getCollection()->each(function (Employee $employee) use ($hasher): void {
            $employee->setAttribute('password_readable', $hasher !== null && $hasher->isReadable((string) $employee->user?->password));
        });

        return view('staff-passwords.index', [
            'employees' => $employees,
            'search' => $search,
            'shown' => $shown,
            'moduleEnabled' => $hasher !== null,
            'logs' => PasswordRevealLog::query()->with(['admin:id,name', 'target:id,name,office_id'])->latest('id')->limit(15)->get(),
        ]);
    }
}
