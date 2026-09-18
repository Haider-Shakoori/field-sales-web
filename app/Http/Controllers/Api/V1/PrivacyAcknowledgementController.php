<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePrivacyAcknowledgementRequest;
use App\Http\Resources\PrivacyAcknowledgementResource;
use App\Models\Device;
use App\Models\PrivacyAcknowledgement;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * GPS tracking policy acknowledgement (privacy consent).
 *
 * Records only acknowledgements explicitly submitted by the authenticated
 * mobile application; never auto-created on login or Start Day. Retries with
 * the same tenant/user/device/policy_version are idempotent.
 */
class PrivacyAcknowledgementController extends Controller
{
    public function store(StorePrivacyAcknowledgementRequest $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required.');

        $device = $request->attributes->get('device');
        abort_if(! $device instanceof Device, 403, 'Device identification is required.');

        $data = $request->validated();

        $acknowledgement = PrivacyAcknowledgement::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $request->user()->id,
                'device_id' => $device->id,
                'policy_version' => $data['policy_version'],
            ],
            [
                'acknowledged_at' => CarbonImmutable::parse($data['acknowledged_at'])->utc(),
                'app_version' => $data['app_version'] ?? null,
            ],
        );

        return ApiResponse::success(
            new PrivacyAcknowledgementResource($acknowledgement),
            status: $acknowledgement->wasRecentlyCreated ? 201 : 200,
        );
    }
}
