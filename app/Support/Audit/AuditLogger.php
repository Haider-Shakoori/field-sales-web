<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an audit log entry.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function log(
        string $event,
        Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        ?User $user = null,
        ?int $tenantId = null,
    ): AuditLog {
        $user ??= request()->user();
        $tenantId ??= TenantContext::currentId() ?? ($user instanceof User ? $user->tenant_id : null);

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => url()->current(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * Record a "sensitive action" event with only the key changes.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function changed(string $event, Model $auditable, array $changes): AuditLog
    {
        $diff = collect($changes)
            ->filter(fn ($value, $key) => $auditable->getAttribute($key) != $value)
            ->mapWithKeys(fn ($value, $key) => [$key => [
                'old' => $auditable->getOriginal($key),
                'new' => $value,
            ]])->all();

        return static::log($event, $auditable, [], $diff);
    }
}
