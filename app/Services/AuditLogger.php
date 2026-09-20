<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function record(
        string $event,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $this->request->user()?->id,
            'event' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_uuid' => $subject->getAttribute('uuid'),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
