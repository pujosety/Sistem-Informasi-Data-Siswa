<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Services\AuditService;
use App\Services\CompletenessService;
use Illuminate\Http\Request;

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
