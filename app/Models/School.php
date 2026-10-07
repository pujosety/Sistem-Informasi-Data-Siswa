<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'slug', 'name', 'short_name', 'npsn', 'level', 'status', 'accreditation',
        'profile', 'contact', 'principal', 'branding', 'homepage', 'social', 'seo',
        'is_active', 'is_default',
    ];

    protected $casts = [
        'profile' => 'array',
        'contact' => 'array',
        'principal' => 'array',
        'branding' => 'array',
        'homepage' => 'array',
        'social' => 'array',
        'seo' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function landingSections(): HasMany
    {
        return $this->hasMany(LandingSection::class);
    }
}
