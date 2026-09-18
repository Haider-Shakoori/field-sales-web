<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Services\MobileAppPolicy;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class DeviceRequired
{
    public function __construct(private readonly MobileAppPolicy $mobilePolicy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $deviceUuid = $request->header('X-Device-UUID');
        $installationUuid = $request->header('X-Installation-UUID');
        $appVersion = $request->header('X-App-Version');
        $platform = strtolower((string) $request->header('X-Platform'));

        if (! $user || ! $deviceUuid || ! $installationUuid || ! $appVersion || ! $platform) {
            return ApiResponse::error(
                'Device headers are required.',
                422,
                null,
                'DEVICE_HEADERS_REQUIRED'
            );
        }

        if (! $this->mobilePolicy->isSupported($appVersion)) {
            return ApiResponse::error(
                'This app version is no longer supported.',
                426,
                $this->mobilePolicy->payload($appVersion),
                'APP_UPGRADE_REQUIRED'
            );
        }

        $device = Device::where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->where('installation_uuid', $installationUuid)
            ->first();

        if (! $device) {
            return ApiResponse::error(
                'This device is not registered.',
                403,
                null,
                'DEVICE_NOT_REGISTERED'
            );
        }

        if ($device->isRevoked()) {
            return ApiResponse::error(
                'This device has been revoked.',
                403,
                null,
                'DEVICE_REVOKED'
            );
        }

        $accessToken = $user->currentAccessToken();
        $hasBearerToken = filled($request->bearerToken());

        if (! $hasBearerToken) {
            $testingTransientToken = app()->environment('testing')
                && $accessToken instanceof TransientToken;

            if (! $testingTransientToken) {
                return ApiResponse::error(
                    'A device-bound bearer token is required.',
                    401,
                    null,
                    'DEVICE_TOKEN_REQUIRED'
                );
            }
        } elseif (! $accessToken instanceof PersonalAccessToken
            || $accessToken->name !== 'mobile-'.$device->uuid) {
            return ApiResponse::error(
                'This access token is not bound to the supplied device.',
                403,
                null,
                'DEVICE_TOKEN_MISMATCH'
            );
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'app_version' => $appVersion,
            'platform' => $platform,
            'os_version' => $request->header('X-OS-Version') ?: $device->os_version,
        ])->saveQuietly();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
