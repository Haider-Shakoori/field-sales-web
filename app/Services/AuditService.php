<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function record(string $event, ?Model $auditable = null, array $metadata = [], ?Request $request = null): AuditLog
    {
        $user = $request?->user();

        return AuditLog::create([
            'tenant_id' => $user?->tenant_id ?? $auditable?->tenant_id,
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
        ]);
    }
}
