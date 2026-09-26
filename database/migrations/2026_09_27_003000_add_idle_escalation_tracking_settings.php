<?php

use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $context = app(TenantContext::class);
        $tenants = $context->withPlatformScope(fn () => Tenant::query()->get(['id']));

        foreach ($tenants as $tenant) {
            $context->withTenant($tenant, function () use ($tenant): void {
                foreach ([
                    'idle_escalation_enabled' => '1',
                    'idle_escalate_after_minutes' => '30',
                ] as $key => $value) {
                    CompanySetting::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                        ['value' => $value],
                    );
                }
            });
        }
    }

    public function down(): void
    {
        CompanySetting::query()
            ->whereIn('key', [
                'tracking.idle_escalation_enabled',
                'tracking.idle_escalate_after_minutes',
            ])
            ->delete();
    }
};
