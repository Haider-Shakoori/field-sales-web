<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DeviceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Device;
use App\Support\Http\ApiResponse;
use App\Support\Mobile\MobileSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mobile authentication endpoints (documented in API_CONTRACT.md §3).
 *
 * The login endpoint performs identity resolution (credentials are matched
 * across tenant boundaries under the system scope), then registers/updates the
 * requesting device inside the user's tenant context — mirroring the device
 * registration semantics from DeviceController: one device-bound Sanctum token
 * per device, rotation on re-authentication, and revocation handling.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        Auth::logout();

        return TenantContext::withSystemScope(function () use ($request): JsonResponse {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
                'device_uuid' => ['required', 'string', 'max:255'],
                'device_model' => ['nullable', 'string', 'max:100'],
                'manufacturer' => ['nullable', 'string', 'max:100'],
                'android_version' => ['nullable', 'string', 'max:20'],
                'app_version' => ['nullable', 'string', 'max:20'],
                'push_token' => ['nullable', 'string', 'max:500'],
            ]);

            $throttleKey = 'api-login:'.$request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return ApiResponse::error(
                    'Too many login attempts. Please try again later.',
                    429,
                    ['seconds' => RateLimiter::availableIn($throttleKey)],
                );
            }

            if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
                RateLimiter::hit($throttleKey, 60);

                return ApiResponse::error('Invalid credentials.', 401);
            }

            $user = $request->user();

            Auth::logout();

            if (! $user->is_active) {
                Auth::logout();

                return ApiResponse::error('Your account has been deactivated. Please contact support.', 403);
            }

            RateLimiter::clear($throttleKey);
            $user->lastLogin();

            $user->load(['tenant', 'branch']);

            $device = $this->resolveOrCreateDevice($request, $user, $credentials);

            return ApiResponse::success([
                'token' => $device['plain_text_token'],
                'user' => new UserResource($user),
                'tenant' => $user->tenant_id !== null ? new TenantResource($user->tenant) : null,
                'permissions' => $user->roles()->with('permissions')->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('name')
                    ->unique()
                    ->values()
                    ->all(),
                'device' => new DeviceResource($device['resource']->loadMissing('salesman')),
            ]);
        });
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()->currentAccessToken();

        $token?->delete();

        return ApiResponse::success();
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $name = $current?->name ?? 'mobile';
        $current?->delete();

        $token = $user->createToken($name, ['*']);

        return ApiResponse::success(['token' => $token->plainTextToken]);
    }

    /**
     * Resolve the device for this login, mirroring DeviceController::register:
     * idempotent upsert keyed by installation/device UUID, revoked rejection,
     * one-device-per-salesman policy on first registration, and rotation of the
     * previous device-bound Sanctum token.
     *
     * @return array{plain_text_token: string, resource: Device}
     */
    private function resolveOrCreateDevice(Request $request, $user, array $credentials): array
    {
        $installationId = $request->header('X-Installation-UUID');

        if ($installationId === null) {
            throw ValidationException::withMessages([
                'device' => 'The X-Installation-UUID header is required.',
            ]);
        }

        $salesman = $user->salesmanProfile;

        $device = $this->withUserTenant($user, function () use ($user, $salesman, $installationId, $credentials): Device {
            $byInstall = Device::query()->where('installation_uuid', $installationId)->first();
            $byDevice = Device::query()->where('device_uuid', $credentials['device_uuid'])->first();

            if ($byInstall !== null && $byDevice !== null && $byInstall->isNot($byDevice)) {
                abort(409, 'Conflict between installation and device identity.');
            }

            $device = $byInstall ?? $byDevice;

            if ($device !== null) {
                if (! $device->isActive()) {
                    throw DeviceException::revoked('This device has been revoked. Reinstall the app or contact support.');
                }

                $device->update([
                    'user_id' => $user->id,
                    'salesman_id' => $salesman?->id,
                    'device_uuid' => $credentials['device_uuid'],
                    'installation_uuid' => $installationId,
                    'device_model' => $credentials['device_model'] ?? null,
                    'manufacturer' => $credentials['manufacturer'] ?? null,
                    'android_version' => $credentials['android_version'] ?? null,
                    'app_version' => $credentials['app_version'] ?? null,
                    'push_token' => $credentials['push_token'] ?? null,
                    'is_active' => true,
                    'revoked_at' => null,
                    'registered_at' => $device->registered_at ?? now('UTC'),
                    'last_seen_at' => now('UTC'),
                ]);

                return $device;
            }

            if (MobileSettings::oneDevicePerSalesman()
                && Device::query()->active()->where('user_id', $user->id)->exists()) {
                abort(422, 'Device limit reached for this salesman. Revoke another device or contact support.');
            }

            return Device::create([
                'tenant_id' => TenantContext::currentId(),
                'user_id' => $user->id,
                'salesman_id' => $salesman?->id,
                'device_uuid' => $credentials['device_uuid'],
                'installation_uuid' => $installationId,
                'device_model' => $credentials['device_model'] ?? null,
                'manufacturer' => $credentials['manufacturer'] ?? null,
                'android_version' => $credentials['android_version'] ?? null,
                'app_version' => $credentials['app_version'] ?? null,
                'push_token' => $credentials['push_token'] ?? null,
                'fcm_token' => null,
                'is_active' => true,
                'registered_at' => now('UTC'),
                'last_seen_at' => now('UTC'),
            ]);
        });

        // Rotate: only one valid Sanctum token named device:{device_uuid} may exist.
        $user->tokens()
            ->where('name', 'device:'.$credentials['device_uuid'])
            ->delete();

        $token = $user->createToken('device:'.$device->device_uuid, ['*'])->plainTextToken;

        return [
            'plain_text_token' => $token,
            'resource' => $device,
        ];
    }

    /**
     * Run device work inside the user's tenant context so tenant scoping
     * matches the authenticated device-registration flow.
     */
    private function withUserTenant($user, callable $callback): mixed
    {
        if ($user->tenant_id !== null && $user->relationLoaded('tenant') && $user->tenant !== null) {
            return app(TenantContext::class)->withTenant($user->tenant, $callback);
        }

        return TenantContext::withSystemScope($callback);
    }
}
