<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceHeartbeatRequest;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Support\Audit\AuditLogger;
use App\Support\Http\ApiResponse;
use App\Support\Mobile\MobileSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $installationId = $request->header('X-Installation-UUID');
        $tenantId = TenantContext::currentId();

        if ($installationId === null) {
            return ApiResponse::error('The X-Installation-UUID header is required.', 422);
        }

        abort_if($tenantId === null, 403, 'A tenant context is required to register a device.');

        $data = $request->validated();
        $salesman = $user->salesmanProfile;

        $byInstall = Device::query()->where('installation_uuid', $installationId)->first();
        $byDevice = Device::query()->where('device_uuid', $data['device_uuid'])->first();

        if ($byInstall !== null && $byDevice !== null && $byInstall->isNot($byDevice)) {
            return ApiResponse::error('Conflict between installation and device identity.', 409);
        }

        $device = $byInstall ?? $byDevice;

        if ($device !== null) {
            if (! $device->isActive()) {
                return ApiResponse::error('This device has been revoked. Reinstall the app or contact support.', 403);
            }

            $device->update([
                'user_id' => $user->id,
                'salesman_id' => $salesman?->id,
                'device_uuid' => $data['device_uuid'],
                'installation_uuid' => $installationId,
                'device_model' => $data['device_model'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'android_version' => $data['android_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'push_token' => $data['push_token'] ?? null,
                'is_active' => true,
                'revoked_at' => null,
                'registered_at' => $device->registered_at ?? now('UTC'),
                'last_seen_at' => now('UTC'),
            ]);

            $device->loadMissing('salesman');
        } else {
            if (MobileSettings::oneDevicePerSalesman()
                && Device::query()->active()->where('user_id', $user->id)->exists()) {
                return ApiResponse::error(
                    'Device limit reached for this salesman. Revoke another device or contact support.',
                    422,
                );
            }

            $device = Device::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'salesman_id' => $salesman?->id,
                'device_uuid' => $data['device_uuid'],
                'installation_uuid' => $installationId,
                'device_model' => $data['device_model'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'android_version' => $data['android_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'push_token' => $data['push_token'] ?? null,
                'fcm_token' => null,
                'is_active' => true,
                'registered_at' => now('UTC'),
                'last_seen_at' => now('UTC'),
            ]);

            AuditLogger::log('device.registered', $device, [], [
                'device_uuid' => $device->device_uuid,
                'installation_uuid' => $device->installation_uuid,
                'device_model' => $device->device_model,
                'user_id' => $device->user_id,
                'salesman_id' => $device->salesman_id,
            ]);
        }

        // Revoke any existing device-bound Sanctum tokens for this user
        // so that only one valid token named device:{device_uuid} exists after registration.
        $user->tokens()
            ->where('name', 'device:'.$device->device_uuid)
            ->delete();

        $token = $user->createToken('device:'.$device->device_uuid, ['*'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'device' => new DeviceResource($device),
        ], status: 201);
    }

    public function heartbeat(DeviceHeartbeatRequest $request): JsonResponse
    {
        $device = $this->resolveDevice($request);

        $user = $request->user();

        abort_if(! $user->can('heartbeat', $device), 403);

        abort_if(! $device->isActive(), 403, 'Device revoked.');

        $data = $request->validated();
        $appVersion = $data['app_version'] ?? $device->app_version;

        $device->update([
            'app_version' => $data['app_version'] ?? $device->app_version,
            'push_token' => $data['push_token'] ?? $device->push_token,
            'last_seen_at' => now('UTC'),
        ]);

        return ApiResponse::success([
            'id' => $device->uuid,
            'last_heartbeat_at' => $device->last_seen_at->toIso8601String(),
            'app_update' => MobileSettings::upgradePayload($appVersion),
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);

        $user = $request->user();

        abort_if(! $user->can('revoke', $device), 403);

        if (! $device->isActive()) {
            return ApiResponse::error('Device is already revoked.', 409);
        }

        $device->revoke();

        $device->user()->firstOrFail()->tokens()
            ->where('name', 'device:'.$device->device_uuid)
            ->delete();

        AuditLogger::log('device.revoked', $device, [], [
            'device_uuid' => $device->device_uuid,
            'installation_uuid' => $device->installation_uuid,
            'user_id' => $device->user_id,
            'salesman_id' => $device->salesman_id,
        ]);

        return ApiResponse::success(new DeviceResource($device));
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user->can('viewAny', Device::class), 403);

        $query = Device::query()->with(['user', 'salesman']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($status = $request->string('status')->toString()) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'revoked') {
                $query->revoked();
            }
        }

        $devices = $query->orderByDesc('last_seen_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(DeviceResource::collection($devices));
    }

    /**
     * Resolve the device referenced by the {device} route parameter. Runs after
     * auth + tenancy middleware, so the tenant scope is guaranteed to apply.
     */
    private function resolveDevice(Request $request): Device
    {
        return Device::query()
            ->where('uuid', $request->route('device'))
            ->firstOrFail();
    }
}
