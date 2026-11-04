<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', Rule::in(Employee::ROLES)],
            'status' => ['nullable', Rule::in(['Active', 'Inactive', 'Suspended'])],
        ]);

        return view('employees.index', [
            'employees' => Employee::query()
                ->with('user')
                ->when(
                    filled($filters['search'] ?? null),
                    function (Builder $query) use ($filters): void {
                        $search = '%'.trim($filters['search']).'%';
                        $query->where(function (Builder $query) use ($search): void {
                            $query->where('name', 'like', $search)
                                ->orWhere('office_id', 'like', $search)
                                ->orWhere('designation', 'like', $search)
                                ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('phone_number', 'like', $search));
                        });
                    },
                )
                ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->where('role', $filters['role']))
                ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'roles' => Employee::ROLES,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('employees.create', [
            'roles' => Employee::ROLES,
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->authorizeAdmin($request);

        return view('employees.profile', [
            'employee' => $employee->load(['user', 'details']),
            'pendingRequest' => $employee->changeRequests()->where('status', 'Pending')->latest()->first(),
            'isProfileComplete' => $employee->isProfileComplete(),
            'isAdminEdit' => true,
        ]);
    }

    public function update(
        Request $request,
        Employee $employee,
        EmployeeProfileController $profiles,
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        return $profiles->updateForAdmin($request, $employee);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->merge([
            'phone_number' => User::normalizePhoneNumber((string) $request->input('phone_number', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'office_id' => ['required', 'string', 'max:50', 'unique:employees,office_id'],
            'designation' => ['required', 'string', 'max:100'],
            'joining_date' => ['required', 'date'],
            'role' => ['required', Rule::in(Employee::ROLES)],
            'phone_number' => ['required', 'string', 'max:25', new PhoneNumber, 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:6', 'max:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'phone_number' => $validated['phone_number'],
                'password' => $validated['password'],
                'must_change_password' => true,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'office_id' => $validated['office_id'],
                'designation' => $validated['designation'],
                'joining_date' => $validated['joining_date'],
                'role' => $validated['role'],
                'status' => 'Active',
            ]);

            $employee->details()->create();
        });

        return redirect()->route('employees.index')
            ->with('status', 'Employee account created. The employee must change the temporary password at first login.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() instanceof User && $request->user()->employee?->isAdmin(), 403);
    }
}
