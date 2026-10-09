<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NavigationItem extends Model
{
    use BelongsToSchool;

    protected $table = 'cms_navigation_items';

    protected $fillable = [
        'label', 'url', 'position', 'is_enabled', 'open_in_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_enabled' => 'boolean',
            'open_in_new_tab' => 'boolean',
        ];
    }

    public static function publicItems(): Collection
    {
        if (! Schema::hasTable('cms_navigation_items')) {
            return new Collection;
        }

        return static::query()
            ->where('is_enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
