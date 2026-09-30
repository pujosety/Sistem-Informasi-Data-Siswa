<?php

namespace App\Exceptions;

use App\Models\GuardianRelationship;
use RuntimeException;

/**
 * Raised by GuardianService when a link cannot be made or removed safely.
 *
 * It is an exception rather than a boolean so the refusal can never be
 * ignored: a caller that forgets to catch it gets a 500, not a silent success
 * that has already stranded a student's portal access. The controller turns
 * each case into a specific message for staff.
 */
class GuardianLinkException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'invalid',
    ) {
        parent::__construct($message);
    }

    public static function duplicate(mixed $guardian, string $relation): self
    {
        $label = GuardianRelationship::RELATIONSHIPS[$relation] ?? $relation;

        return new self(
            "Akun tersebut sudah tertaut sebagai {$label} untuk siswa ini.",
            'duplicate',
        );
    }

    public static function lastGuardian(GuardianRelationship $link): self
    {
        return new self(
            'Ini satu-satunya wali murid siswa ini. Tautkan wali murid lain terlebih dahulu sebelum melepas tautan ini.',
            'last_guardian',
        );
    }

    public static function unknownRelation(string $relation): self
    {
        return new self(
            "Hubungan wali murid tidak dikenal: {$relation}.",
            'unknown_relation',
        );
    }
}
