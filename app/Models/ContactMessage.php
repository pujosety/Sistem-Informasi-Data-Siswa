<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const NEW = 'new';
    public const READ = 'read';
    public const RESOLVED = 'resolved';

    protected $fillable = [
        'name', 'email', 'phone', 'topic', 'message', 'status', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
