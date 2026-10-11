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
            $request = request();
            $selectorKey = '__primary__';

            // The school switch is an admin capability, not a public query
            // selector. Restore its session value only for Super Admin requests.
            if ($request->hasSession() && $request->user()?->isSuperAdmin()) {
                $selectorKey = trim((string) $request->session()->get('active_school_slug', '')) ?: '__primary__';
            }

            if ($this->requestSelector !== $selectorKey) {
                $this->requestSelector = $selectorKey;
                $this->school = null;
            }
        }

        if ($this->school) {
            return $this->school;
        }

        if ($this->requestSelector !== null && $this->requestSelector !== '__primary__') {
            $selected = School::query()
                ->where('is_active', true)
                ->where('slug', $this->requestSelector)
                ->first();

            if ($selected) {
                return $this->school = $selected;
            }

            // A deleted/deactivated session selection is not permission to
            // fall through to a different tenant with a similar identifier.
            $this->requestSelector = '__primary__';
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

    /** Set tenant scope for this request only; unlike use(), do not persist it in the session. */
    public function useForRequest(School $school): School
    {
        $this->manualOverride = true;
        $this->school = $school;
        $this->requestSelector = $school->slug;

        return $this->school;
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

    /** Clear process-local state after a database/request reset. */
    public static function resetProcessState(): void
    {
        static::$tableExists = null;
        static::$consoleSchoolId = null;
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
