<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Schema;

/** Resolves the school whose settings/content the current request may read. */
class SchoolContext
{
    private ?School $school = null;
    private ?string $requestSelector = null;
    private bool $manualOverride = false;
    private static ?bool $tableExists = null;
    private static ?int $consoleSchoolId = null;

    public function current(): ?School
    {
        if ($this->manualOverride && $this->school) {
            return $this->school;
        }

        if (app()->bound('request')) {
            $selectorKey = '__primary__';

            if ($this->requestSelector !== $selectorKey) {
                $this->requestSelector = $selectorKey;
                $this->school = null;
            }
        }

        if ($this->school) {
            return $this->school;
        }

        if (static::$tableExists !== true && Schema::hasTable('schools')) {
            static::$tableExists = true;
        }

        if (static::$tableExists !== true) {
            return null;
        }

        if ((app()->runningInConsole() || app()->environment('testing')) && static::$consoleSchoolId !== null && ! app()->bound('request')) {
            return $this->school = School::query()->find(static::$consoleSchoolId);
        }

        $query = School::query()->where('is_active', true);
        $this->school = $query->where('is_default', true)->first();

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
        $this->manualOverride = true;
        $this->school = match (true) {
            $school instanceof School => $school,
            is_int($school) => School::query()->find($school),
            is_string($school) => School::query()->where('slug', $school)->first(),
            default => null,
        };

        if ($this->school && app()->bound('request') && function_exists('session')) {
            session(['active_school_slug' => $this->school->slug]);
            $this->requestSelector = $this->school->slug;
        }

        if (app()->runningInConsole() || app()->environment('testing')) {
            static::$consoleSchoolId = $this->school?->id;
        }

        return $this->school;
    }

    public function reset(): void
    {
        $this->school = null;
        $this->manualOverride = false;
        $this->requestSelector = null;
        static::$consoleSchoolId = null;
    }

    public function allActive()
    {
        return School::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }
}
