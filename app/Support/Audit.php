<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;

final class Audit
{
    public static function log(string $action, ?object $subject = null, array $meta = []): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject !== null && isset($subject->id) ? (int) $subject->id : null,
            'meta' => $meta === [] ? null : $meta,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
