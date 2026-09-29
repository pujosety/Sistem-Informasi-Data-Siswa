<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the module registry and answers "is this switched on?".
 *
 * §50 requires that disabling a module leaves no broken navigation link, so
 * every navigation surface has to consult this. NavigationService is the one
 * place navigation is assembled, which means one filter there covers the
 * sidebar, the mobile dock, the overflow menu and search — rather than each
 * view deciding for itself and eventually disagreeing.
 */
class ModuleService
{
    /**
     * Cached per request.
     *
     * Navigation is built on every authenticated render. Without this the
     * registry would be queried once per view composer, and navigation is
     * composed for `*` — so every page, every time.
     */
    private ?array $enabled = null;

    /**
     * @return array<string, true>
     */
    public function enabledKeys(): array
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }

        $this->enabled = Module::query()
            ->where('is_enabled', true)
            ->pluck('key')
            ->mapWithKeys(fn (string $key) => [$key => true])
            ->all();

        return $this->enabled;
    }

    public function isEnabled(string $key): bool
    {
        return isset($this->enabledKeys()[$key]);
    }

    /**
     * Whether a navigation item's module is on.
     *
     * An item with no `module` key is not module-gated and is always allowed —
     * that is how an item that predates the registry keeps working rather than
     * disappearing because nobody annotated it.
     */
    public function allows(?string $key): bool
    {
        return $key === null || $this->isEnabled($key);
    }

    public function find(string $key): ?Module
    {
        return Module::query()->where('key', $key)->first();
    }

    /**
     * Turn a module on or off.
     *
     * A required module cannot be switched off. Silently ignoring the request
     * would leave the operator thinking it worked, and the write would need
     * undoing by hand — so it is refused with a reason.
     */
    public function setEnabled(string $key, bool $enabled): Module
    {
        $module = $this->find($key);

        if (! $module) {
            throw new \InvalidArgumentException("Unknown module [{$key}].");
        }

        if (! $enabled && $module->is_required) {
            throw new \LogicException(
                "Module [{$key}] is required and cannot be disabled."
            );
        }

        $module->update(['is_enabled' => $enabled]);

        $this->enabled = null;

        return $module->refresh();
    }

    /**
     * Forget the per-request cache.
     *
     * Called after a toggle so the very next navigation build sees the new
     * value. Without it an operator would switch a module off, navigate, and
     * find the section still there.
     */
    public function forget(): void
    {
        $this->enabled = null;
    }
}
