<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'hint', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** Cast the raw string into the declared type. */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'bool', 'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => $this->value === null ? null : (int) $this->value,
            'float' => $this->value === null ? null : (float) $this->value,
            'json', 'array' => json_decode((string) $this->value, true) ?: [],
            default => $this->value,
        };
    }

    public static function group(string $group): string
    {
        return match ($group) {
            'general' => 'Aplikasi',
            'branding' => 'Tampilan & Branding',
            'school' => 'Profil Sekolah',
            'registration' => 'Pendaftaran',
            default => ucfirst($group),
        };
    }
}
