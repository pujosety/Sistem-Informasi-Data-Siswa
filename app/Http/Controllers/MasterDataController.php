<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\CompletenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends BaseController
{
    public function __construct(AuditService $audit, CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    public function index()
    {
        return view('admin.master', [
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'classes' => SchoolClass::with(['academicYear', 'department'])->orderBy('name')->get(),
            'documentTypes' => \App\Models\DocumentType::orderBy('sort_order')->get(),
        ]);
    }

    public function storeYear(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['is_active'] ?? false) {
            AcademicYear::query()->update(['is_active' => false]);
        }

        $year = AcademicYear::create($data + ['is_active' => $request->boolean('is_active')]);
        $this->audit->log('master.academic_year_created', $year, 'Tahun ajaran '.$year->name);

        return $this->backWith('Tahun ajaran '.$year->name.' ditambahkan.');
    }

    /**
     * Correct a master record.
     *
     * Master data had `master.create` and no edit at all, so `master.update`
     * was one of the dead permissions: an operator could add a class and then
     * be unable to correct a typo in its name, its level or its capacity, ever.
     * The only way out was a second row with the corrected value.
     *
     * Every edit here refuses a value that would collide with an existing row,
     * ignoring ITSELF. A typo fixed into a name another class already uses is
     * rejected rather than silently duplicating a class.
     */
    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('master.update'), 403);

        return match ($type) {
            'tahun-ajaran' => $this->updateYear($request, $id),
            'jurusan' => $this->updateDepartment($request, $id),
            'kelas' => $this->updateClass($request, $id),
            'jenis-dokumen' => $this->updateDocumentType($request, $id),
            default => abort(404, 'Jenis master data tidak dikenal.'),
        };
    }

    private function updateYear(Request $request, int $id): RedirectResponse
    {
        $year = AcademicYear::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'name')->ignore($id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Only ONE year may be active. Flipping this one on has to clear the
        // others, exactly as creating an active year does, or the dashboard
        // would be reading an "active" year chosen by insert order.
        if ($request->boolean('is_active')) {
            AcademicYear::query()->whereKeyNot($id)->update(['is_active' => false]);
        }

        $year->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->audit->log('master.academic_year_updated', $year, 'Mengubah tahun ajaran '.$year->name);

        return $this->backWith('Tahun ajaran '.$year->name.' diperbarui.');
    }

    private function updateDepartment(Request $request, int $id): RedirectResponse
    {
        $department = Department::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($id)],
        ]);

        $department->update($data);
        $this->audit->log('master.department_updated', $department, 'Mengubah jurusan '.$department->name);

        return $this->backWith('Jurusan diperbarui.');
    }

    private function updateClass(Request $request, int $id): RedirectResponse
    {
        $class = SchoolClass::findOrFail($id);

        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:50', Rule::unique('classes', 'name')->ignore($id)],
            'level' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $class->update($data);
        $this->audit->log('master.class_updated', $class, 'Mengubah kelas '.$class->name);

        return $this->backWith('Kelas '.$class->name.' diperbarui.');
    }

    private function updateDocumentType(Request $request, int $id): RedirectResponse
    {
        $type = DocumentType::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('document_types', 'slug')->ignore($id)],
            'label' => ['nullable', 'string', 'max:100'],
            'accepted_mimes' => ['nullable', 'array'],
            'max_size_kb' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! empty($data['accepted_mimes'])) {
            $data['accepted_mimes'] = array_values(array_map('strtolower', $data['accepted_mimes']));
        }

        $type->update($data + [
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log('master.document_type_updated', $type, 'Mengubah jenis dokumen '.$type->name);

        return $this->backWith('Jenis dokumen diperbarui.');
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
        ]);

        $department = Department::create($data);
        $this->audit->log('master.department_created', $department, 'Jurusan '.$department->name);

        return $this->backWith('Jurusan ditambahkan.');
    }

    public function storeClass(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:50'],
            'level' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $class = SchoolClass::create($data);
        $this->audit->log('master.class_created', $class, 'Kelas '.$class->name);

        return $this->backWith('Kelas ditambahkan.');
    }

    public function storeDocumentType(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_size_kb' => ['required', 'integer', 'min:100', 'max:10240'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        $data['is_required'] = $request->boolean('is_required', true);
        $data['sort_order'] = (int) \App\Models\DocumentType::max('sort_order') + 1;
        $data['accepted_mimes'] = ['jpg', 'jpeg', 'png', 'pdf'];

        $type = \App\Models\DocumentType::create($data);
        $this->audit->log('master.document_type_created', $type, 'Jenis dokumen '.$type->name);

        return $this->backWith('Jenis dokumen ditambahkan.');
    }
}
