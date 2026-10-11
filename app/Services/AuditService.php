<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(string $action, mixed $subject = null, ?string $description = null, array $properties = []): ActivityLog
    {
        return $this->write(Auth::id(), $action, $subject, $description, $properties);
    }

    /** Record an action performed by a validated bearer-token principal. */
    public function logAs(int $actorId, string $action, mixed $subject = null, ?string $description = null, array $properties = []): ActivityLog
    {
        return $this->write($actorId, $action, $subject, $description, $properties);
    }

    private function write(?int $actorId, string $action, mixed $subject, ?string $description, array $properties): ActivityLog
    {
        $type = null;
        $id = null;

        if (is_object($subject) && method_exists($subject, 'getKey')) {
            $type = class_basename($subject);
            $id = $subject->getKey();
        } elseif (is_array($subject) && isset($subject['id'])) {
            $type = $subject['type'] ?? null;
            $id = $subject['id'];
        }

        return ActivityLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
