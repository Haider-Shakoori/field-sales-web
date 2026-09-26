<?php

namespace App\Services\BusinessOs;

use App\Contracts\BusinessOsConnector;
use App\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class HttpBusinessOsConnector implements BusinessOsConnector
{
    public function health(Tenant $tenant): array
    {
        return $this->client($tenant)
            ->get((string) config('businessos.paths.health'))
            ->throw()
            ->json();
    }

    public function pullMasterData(
        Tenant $tenant,
        array $types,
        ?string $cursor = null,
    ): array {
        return $this->client($tenant)
            ->get((string) config('businessos.paths.master_data'), [
                'types' => implode(',', array_values(array_unique($types))),
                'cursor' => $cursor,
            ])
            ->throw()
            ->json();
    }

    public function pushEvents(Tenant $tenant, array $events): array
    {
        return $this->client($tenant)
            ->post((string) config('businessos.paths.events'), [
                'events' => $events,
            ])
            ->throw()
            ->json();
    }

    private function client(Tenant $tenant): PendingRequest
    {
        $organizationKey = trim((string) data_get(
            $tenant->settings,
            'businessos.organization_key',
            '',
        ));

        return Http::baseUrl(rtrim((string) config('businessos.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('businessos.token'))
            ->withHeaders([
                'X-FieldPulse-Tenant' => $tenant->uuid,
                'X-BusinessOS-Organization' => $organizationKey,
            ])
            ->timeout((int) config('businessos.timeout_seconds', 20))
            ->retry(2, 250, throw: false);
    }
}
