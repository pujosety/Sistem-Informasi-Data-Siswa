<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\Student;

class CompletenessService
{
    /**
     * Recompute the 0..100 completeness of a registration:
     * 50% biodata, 20% parent data, 30% required documents uploaded.
     */
    public function compute(Student $student, ?Registration $registration = null): int
    {
        $registration ??= $student->registration;

        $biodata = $this->biodataScore($student);
        $parents = $this->parentScore($student);
        $documents = $this->documentScore($registration);

        return (int) round(($biodata * 0.5) + ($parents * 0.2) + ($documents * 0.3));
    }

    public function refresh(Registration $registration): int
    {
        $score = $this->compute($registration->student, $registration);
        $registration->update(['completeness' => $score]);

        return $score;
    }

    private function biodataScore(Student $student): int
    {
        $fields = [
            'nisn', 'full_name', 'gender', 'birth_place', 'birth_date',
            'religion', 'phone', 'address', 'city',
        ];

        $filled = collect($fields)->filter(fn ($f) => filled($student->{$f}))->count();

        return (int) round($filled / count($fields) * 100);
    }

    private function parentScore(Student $student): int
    {
        $required = ['father', 'mother'];
        $present = collect($required)
            ->filter(fn ($rel) => $student->parents()->where('relation', $rel)->exists())
            ->count();

        return (int) round($present / count($required) * 100);
    }

    private function documentScore(?Registration $registration): int
    {
        $types = DocumentType::where('is_active', true)->where('is_required', true)->count();

        if ($types === 0 || ! $registration) {
            return 0;
        }

        $uploaded = DocumentType::where('is_active', true)
            ->where('is_required', true)
            ->whereHas('documents', fn ($q) => $q->where('registration_id', $registration->id))
            ->count();

        return (int) round($uploaded / $types * 100);
    }
}
