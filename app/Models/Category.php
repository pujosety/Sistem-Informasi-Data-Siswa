<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A post category, and a page section.
 *
 * Pages are usually organised as a tree, which §7 expresses through Post's
 * parent_id. Categories exist for posts, and are shared because a school
 * organising its site will otherwise end up with two vocabularies for the same
 * idea.
 */
class Category extends Model
{
    protected $table = 'cms_categories';

    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function publishedPostCount(): int
    {
        return $this->posts()->publishedAndPublic()->count();
    }
}
