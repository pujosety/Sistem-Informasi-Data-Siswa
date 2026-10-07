<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToSchool;

/**
 * A free-form label on a post.
 */
class Tag extends Model
{
    use BelongsToSchool;

    protected $table = 'cms_tags';

    protected $fillable = ['school_id', 'name', 'slug'];

    public function posts(): BelongsToMany
    {
        // Mirrors Post::tags() — same table, keys stated from both sides.
        return $this->belongsToMany(
            Post::class,
            'cms_post_tag',
            'cms_tag_id',
            'cms_post_id'
        );
    }

    /**
     * Turn free text into a usable slug.
     *
     * Indonesian school content is full of names with spaces, dots and
     * apostrophes, and a tag created from one of those would otherwise produce
     * a URL that has to be encoded in every link.
     */
    public static function slugify(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);

        return $slug !== '' ? $slug : 'tag';
    }
}
