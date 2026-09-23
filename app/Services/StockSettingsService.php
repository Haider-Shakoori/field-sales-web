<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Tenant;

class StockSettingsService
{
    public function enabled(Tenant $tenant): bool
    {
        $value = CompanySetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'inventory.salesman_stock_enabled')
            ->value('value');

        return filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(Tenant $tenant, bool $enabled): void
    {
        CompanySetting::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'key' => 'inventory.salesman_stock_enabled',
            ],
            [
                'value' => $enabled ? '1' : '0',
            ],
        );
    }
}
