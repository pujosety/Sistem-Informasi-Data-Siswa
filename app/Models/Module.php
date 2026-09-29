<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A switchable section of the platform.
 *
 * Not a plugin: a module is a row the registry knows about, and toggling it
 * hides navigation rather than executing code. §50 rules out arbitrary PHP
 * plugin execution, and this model has no field that could carry one.
 */
class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'key', 'name', 'description',
        'is_enabled', 'is_required', 'route_prefix', 'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Whether this module may be switched off.
     *
     * Required modules are the platform: with students or academic off there is
     * no way to record a student or a grade, so the toggle is refused rather
     * than ignored.
     */
    public function canBeDisabled(): bool
    {
        return ! $this->is_required;
    }
}
