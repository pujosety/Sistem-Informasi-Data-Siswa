<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ADMIN → KEPEGAWAIAN (HRIS, Phase 9)
 *
 * The `employees` table and EmployeeService landed in Phase 3 with a deliberate
 * decision attached: no backfill. Existing staff are `users` with a role, and
 * only the school knows which of them is a teacher, a clerk or a vice
 * principal, so the migration refused to guess. That left the module
 * unusable — the records existed and nothing could read or write them.
 *
 * This controller is the missing half, and it is also where that "no backfill"
 * decision finally gets to be applied by hand, which is the only honest way
 * to do it.
 *
 * THE TWO RULES THIS ENFORCES
 *
 * 1. Identity is never copied. Name, email and phone live on `users` and are
 *    read from the linked account; this form writes only employment. A form
 *    that offered a "name" field here would be the first step toward a second
 *    source of truth that disagrees the first time somebody changes their
 *    phone number.
 *
 * 2. A resignation is a status change, never a delete. The person wrote the
 *    grade history and holds the homeroom assignments; cascading that away is
 *    why `resign()` exists instead of `delete()`.
 *
 * The account is optional on purpose: a school hires a teacher months before
 * it issues them a login, and the employment record should exist from day one.
 */
class EmployeeController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly EmployeeService $employees,
    ) {
        parent::__construct($audit, $completeness);
    }

    // ------------------------------------------------------------------ read

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with(['user.roles', 'department'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(function ($w) use ($term) {
                // The name lives on the linked account, so a staff search has
                // to reach through the relation — searching `employees` alone
                // would silently find nothing for every real employee.
                $w->whereHas('user', fn ($u) => $u
                        ->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%'))
                    ->orWhere('employee_number', 'like', '%'.$term.'%')
                    ->orWhere('position', 'like', '%'.$term.'%');
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('employment_status', $request->string('status')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->orderByRaw("CASE WHEN employment_status = 'resigned' THEN 1 ELSE 0 END")
            ->orderBy('employee_number')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'statuses' => Employee::STATUSES,
            'q' => $request->string('q')->toString(),
            'filters' => $request->only(['status', 'department']),
            // Shown as a standing reminder: the no-backfill decision means the
            // staff list is only as complete as the school has made it.
            'unrecordedCount' => $this->staffWithoutRecord(),
        ]);
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        return view('admin.employees.show', [
            'employee' => $employee->load(['user.roles', 'department', 'creator']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Employee::class);

        return view('admin.employees.create', $this->formOptions([
            'employee' => new Employee([
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
            ]),
            // Pre-selecting the account from ?user= is how the "link a login to
            // an existing staff member" flow is reached.
            'selectedUserId' => $request->integer('user') ?: null,
        ]));
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('admin.employees.edit', $this->formOptions(['employee' => $employee]));
    }

    // ----------------------------------------------------------------- write

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $actor = $request->user();

        /*
         | `recordFor()` is upsert-shaped, so POSTing twice for one account must
         | update the existing employment rather than create a second one. The
         | unique rule therefore has to ignore whichever row already belongs to
         | that user — with no id to ignore, the second POST failed validation
         | and the position silently stayed at the first value, which reads as
         | "the edit did nothing" rather than "the request was rejected".
         */
        $existing = Employee::query()->where('user_id', (int) $request->input('user_id'))->first();

        $data = $request->validate($this->rules($existing));

        // recordFor() keys on user_id, so this is upsert-shaped: a payload that
        // names an account which already has a record updates that record
        // rather than creating a second employment for one person.
        $user = User::findOrFail($data['user_id']);

        $employee = $this->employees->recordFor($user, $this->employmentAttributes($data, $actor));

        $this->audit->log('employee.created', $employee, "Membuat catatan kepegawaian untuk {$employee->displayName()}", [
            'employee_number' => $employee->employee_number,
            'department' => $employee->department?->name,
        ]);

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('success', 'Catatan kepegawaian tersimpan.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $actor = $request->user();

        $data = $request->validate($this->rules($employee));

        /*
         | The linked account is deliberately NOT editable from this form.
         |
         | Changing `user_id` on an existing record is a re-attribution, not an
         | edit: it would silently move a contract, a department and a hire
         | date onto a different person, and the history that follows the
         | employment would no longer belong to the person who earned it. The
         | form omits the field and this guard refuses a crafted payload.
         */
        if ($request->filled('user_id') && (int) $request->input('user_id') !== (int) $employee->user_id) {
            return $this->backWith(
                'Akun tertaut tidak dapat diganti. Buat catatan baru untuk orang yang berbeda.',
                'error'
            );
        }

        $employee->update($this->employmentAttributes($data, $actor));

        $this->audit->log('employee.updated', $employee, "Mengubah data kepegawaian {$employee->displayName()}");

        return $this->backWith('Data kepegawaian diperbarui.');
    }

    /**
     * End the employment. The row stays.
     */
    public function resign(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('resign', $employee);

        if (! $employee->isCurrent()) {
            return $this->backWith('Hubungan kerja ini sudah berakhir.', 'error');
        }

        $data = $request->validate([
            'resignation_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'resignation_reason.max' => 'Alasan terlalu panjang (maksimal 255 karakter).',
        ]);

        $this->employees->resign($employee, $data['resignation_reason'] ?? null);

        $this->audit->log('employee.resigned', $employee, "Mengakhiri hubungan kerja {$employee->displayName()}", [
            'reason' => $data['resignation_reason'] ?? null,
        ]);

        return $this->backWith('Hubungan kerja dicatat berakhir. Riwayat nilai dan tugasnya tetap tersimpan.');
    }

    /**
     * Put a resigned person back on the staff list.
     *
     * `resigned_at` and the reason are cleared because the row is the current
     * employment, and a record that claims someone resigned while the status
     * says active is two contradictory facts in one place. The resignation
     * itself remains in `activity_logs`, which is where history belongs.
     */
    public function reinstate(Employee $employee): RedirectResponse
    {
        $this->authorize('reinstate', $employee);

        if ($employee->isCurrent()) {
            return $this->backWith('Pegawai ini masih aktif.', 'error');
        }

        $employee->update([
            'employment_status' => Employee::ACTIVE,
            'resigned_at' => null,
            'resignation_reason' => null,
        ]);

        $this->audit->log('employee.reinstated', $employee, "Mengaktifkan kembali kepegawaian {$employee->displayName()}");

        return $this->backWith('Kepegawaian diaktifkan kembali.');
    }

    // ------------------------------------------------------------------ help

    /**
     * Validation rules, shared by store and update.
     *
     * `employee_number` is unique but nullable: a school is allowed to hire
     * before it issues a staff number, and a plain unique index would then
     * permit only one unnumbered employee.
     */
    private function rules(?Employee $employee = null): array
    {
        return [
            'user_id' => [
                // Always required, even on an upsert: the form always posts
                // it, and making it optional here would let a payload that
                // omits it slip past validation into recordFor() as null.
                'required',
                'integer',
                'exists:users,id',
                // One employment per account. recordFor() upserts, so a
                // duplicate here would silently overwrite somebody's contract.
                Rule::unique('employees', 'user_id')->ignore($employee?->id),
            ],
            'employee_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('employees', 'employee_number')->ignore($employee?->id),
            ],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['required', Rule::in(Employee::STATUSES)],
            'employment_type' => ['required', 'string', 'max:30'],
            'hire_date' => ['nullable', 'date'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'work_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The columns this module is allowed to write.
     *
     * Excludes `resigned_at` / `resignation_reason` on purpose: those belong to
     * resign() and reinstate(), so an ordinary form edit cannot rewrite a
     * termination date to hide one.
     */
    private function employmentAttributes(array $data, User $actor): array
    {
        return [
            'employee_number' => $data['employee_number'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'employment_status' => $data['employment_status'],
            'employment_type' => $data['employment_type'],
            'hire_date' => $data['hire_date'] ?? null,
            'contract_start' => $data['contract_start'] ?? null,
            'contract_end' => $data['contract_end'] ?? null,
            'work_address' => $data['work_address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ];
    }

    private function formOptions(array $extra = []): array
    {
        return $extra + [
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'statuses' => Employee::STATUSES,
            /*
             * Staff accounts that do not yet have an employment record.
             *
             * The candidate list is deliberately restricted to accounts holding
             * a staff role, so that linking an employment to a student's login
             * is not one dropdown away. EmployeeService::isStaff() is the same
             * definition used everywhere else in the module.
             */
            'linkableUsers' => User::query()
                ->whereDoesntHave('employee')
                ->whereHas('roles', fn ($r) => $r->whereIn('name', self::staffRoles()))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'statusLabels' => [
                Employee::ACTIVE => 'Aktif',
                Employee::INACTIVE => 'Tidak Aktif',
                Employee::ON_LEAVE => 'Cuti',
                Employee::RESIGNED => 'Berhenti',
            ],
            'typeLabels' => [
                'permanent' => 'Tetap',
                'contract' => 'Kontrak',
                'honorary' => 'Honorarium',
                'part_time' => 'Paruh Waktu',
            ],
        ];
    }

    /**
     * Staff accounts with no employment record.
     *
     * Reported, not fixed. This is the backfill the Phase 3 migration refused
     * to perform, and the number is the school's to act on: it is the size of
     * the job still to be keyed in.
     */
    private function staffWithoutRecord(): int
    {
        return User::query()
            ->whereDoesntHave('employee')
            ->whereHas('roles', fn ($r) => $r->whereIn('name', self::staffRoles()))
            ->count();
    }

    /** @return string[] */
    private static function staffRoles(): array
    {
        return [
            'super_admin', 'admin', 'kesiswaan', 'operator', 'verifikator',
            'wali_kelas', 'principal', 'vice_principal', 'academic_staff',
            'staff', 'cms_editor', 'cms_publisher', 'lms_teacher', 'counselor',
        ];
    }
}
