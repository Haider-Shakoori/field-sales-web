<?php

namespace App\Services\BusinessOs;

use App\Contracts\BusinessOsConnector;
use App\Models\Tenant;

final class NullBusinessOsConnector implements BusinessOsConnector
{
    public function health(Tenant $tenant): array
    {
        return [
            'connected' => false,
            'status' => 'disabled',
            'tenant_id' => $tenant->uuid,
        ];
    }

    public function pullMasterData(
        Tenant $tenant,
        array $types,
        ?string $cursor = null,
    ): array {
        return [
            'status' => 'disabled',
            'data' => [],
            'cursor' => $cursor,
        ];
    }

    public function pushEvents(Tenant $tenant, array $events): array
    {
        return [
            'status' => 'disabled',
            'accepted' => 0,
        ];
    }
}
