<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Schema;

/** Resolves the school whose settings/content the current request may read. */
class SchoolContext
{
    private ?School $school = null;
    private ?int $requestObjectId = null;
    private static ?bool $tableExists = null;
    private static ?int $consoleSchoolId = null;

    public function current(): ?School
    {
        if (app()->bound('request')) {
            $requestId = spl_object_id(request());
            if ($this->requestObjectId !== $requestId) {
                $this->requestObjectId = $requestId;
                $this->school = null;
            }
        }

        if ($this->school) {
            return $this->school;
        }

        if (! (static::$tableExists ??= Schema::hasTable('schools'))) {
            return null;
        }

        if ((app()->runningInConsole() || app()->environment('testing')) && static::$consoleSchoolId !== null) {
            return $this->school = School::query()->find(static::$consoleSchoolId);
        }

        $slug = null;
        if (function_exists('request') && app()->bound('request')) {
            $slug = request()->query('school') ?: (function_exists('session') ? session('active_school_slug') : null);
            if ($slug && function_exists('session')) {
                session(['active_school_slug' => $slug]);
            }
        }

        $query = School::query()->where('is_active', true);
        $this->school = $slug
            ? $query->where('slug', $slug)->first()
            : $query->where('is_default', true)->first();

        return $this->school ?: $query->orderBy('id')->first();
    }

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    public function slug(): ?string
    {
        return $this->current()?->slug;
    }

    public function use(School|int|string|null $school): ?School
    {
        $this->school = match (true) {
            $school instanceof School => $school,
            is_int($school) => School::query()->find($school),
            is_string($school) => School::query()->where('slug', $school)->first(),
            default => null,
        };

        if ($this->school && app()->bound('request') && function_exists('session')) {
            session(['active_school_slug' => $this->school->slug]);
        }

        if (app()->runningInConsole() || app()->environment('testing')) {
            static::$consoleSchoolId = $this->school?->id;
        }

        return $this->school;
    }

    public function reset(): void
    {
        $this->school = null;
    }

    public function allActive()
    {
        return School::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }
}
