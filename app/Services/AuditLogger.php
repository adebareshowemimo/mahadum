<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Central recorder for sensitive-action audit entries (money movements, auth
 * changes, institutional actions). Pulls the acting user + IP from the current
 * request so callers only describe *what* happened. In webhook/console contexts
 * there is no authenticated actor — the entry is recorded with a null actor.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $action, ?Model $subject = null, array $before = [], array $after = [], ?int $organizationId = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'organization_id' => $organizationId,
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip' => $request->ip(),
        ]);
    }
}
