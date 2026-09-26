<?php

namespace App\Contracts;

use App\Models\Tenant;

interface BusinessOsConnector
{
    public function health(Tenant $tenant): array;

    public function pullMasterData(
        Tenant $tenant,
        array $types,
        ?string $cursor = null,
    ): array;

    public function pushEvents(Tenant $tenant, array $events): array;
}
