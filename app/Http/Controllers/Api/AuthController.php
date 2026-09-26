<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\MobileAppPolicy;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(
        Request $request,
        TenantContext $context,
        MobileAppPolicy $mobilePolicy,
    ) {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant' => ['nullable', 'string', 'max:191'],
            'device_uuid' => ['nullable', 'string', 'max:191'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'manufacturer' => ['nullable', 'string', 'max:191'],
            'android_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'push_token' => ['nullable', 'string', 'max:1000'],
        ]);

        $matches = $context->withAuthenticationBootstrapScope(function () use ($validated) {
            return User::with(['tenant', 'salesman', 'supervisor', 'roles.permissions'])
                ->where('email', $validated['email'])
                ->when(
                    $validated['tenant'] ?? null,
                    fn ($query, $tenant) => $query->whereHas(
                        'tenant',
                        fn ($tenantQuery) => $tenantQuery
                            ->where('uuid', $tenant)
                            ->orWhere('slug', $tenant)
                    )
                )
                ->limit(2)
                ->get();
        });

        if ($matches->count() > 1 && empty($validated['tenant'])) {
            return ApiResponse::error(
                'This email belongs to more than one company.',
                422,
                ['tenant' => ['Provide the company UUID or slug.']],
                'TENANT_REQUIRED'
            );
        }

        /** @var User|null $user */
        $user = $matches->first();

        if (! $user || ! Hash::check($validated['password'], $user->password) || ! $user->is_active) {
            return ApiResponse::error(
                'Invalid credentials.',
                422,
                ['email' => ['The provided credentials are incorrect.']],
                'INVALID_CREDENTIALS'
            );
        }

        $mobileRoles = ['salesman', 'supervisor', 'sales_manager', 'owner', 'company_admin'];
        $roleSlugs = $user->roles->pluck('slug');

        if (! $roleSlugs->contains(fn (string $role): bool => in_array($role, $mobileRoles, true))) {
            return ApiResponse::error(
                'This account role is not enabled for the mobile app.',
                403,
                null,
                'MOBILE_ROLE_UNSUPPORTED'
            );
        }

        if ($roleSlugs->contains('salesman') && (! $user->salesman || ! $user->salesman->is_active)) {
            return ApiResponse::error(
                'No active salesman profile is linked to this user.',
                422,
                null,
                'SALESMAN_REQUIRED'
            );
        }

        if ($roleSlugs->contains('supervisor') && (! $user->supervisor || ! $user->supervisor->is_active)) {
            return ApiResponse::error(
                'No active supervisor profile is linked to this user.',
                422,
                null,
                'SUPERVISOR_REQUIRED'
            );
        }

        if ($user->tenant?->subscription_status !== 'active') {
            return ApiResponse::error(
                'This company account is suspended.',
                403,
                null,
                'TENANT_SUSPENDED'
            );
        }

        $installationUuid = $request->header('X-Installation-UUID');
        $deviceUuid = $request->header('X-Device-UUID') ?: ($validated['device_uuid'] ?? null);
        $appVersion = $request->header('X-App-Version') ?: ($validated['app_version'] ?? null);
        $platform = strtolower((string) ($request->header('X-Platform') ?: 'android'));
        $osVersion = $request->header('X-OS-Version') ?: ($validated['android_version'] ?? null);

        if (! $installationUuid || ! $deviceUuid) {
            return ApiResponse::error(
                'X-Device-UUID and X-Installation-UUID are required.',
                422,
                null,
                'DEVICE_HEADERS_REQUIRED'
            );
        }

        if (! $mobilePolicy->isSupported($appVersion)) {
            return ApiResponse::error(
                'This app version is no longer supported.',
                426,
                $mobilePolicy->payload($appVersion),
                'APP_UPGRADE_REQUIRED'
            );
        }

        $context->initializeTenant((int) $user->tenant_id);

        $existing = Device::where('user_id', $user->id)
            ->where('installation_uuid', $installationUuid)
            ->first();

        if ($existing?->isRevoked()) {
            return ApiResponse::error(
                'This device has been revoked.',
                403,
                null,
                'DEVICE_REVOKED'
            );
        }

        $other = Device::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->first();

        if ($other) {
            return ApiResponse::error(
                'Only one active device is allowed for this salesman.',
                422,
                [
                    'active_device' => [
                        'id' => $other->uuid,
                        'model' => $other->device_model,
                        'last_seen_at' => $other->last_seen_at?->toIso8601String(),
                    ],
                ],
                'DEVICE_LIMIT_REACHED'
            );
        }

        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'installation_uuid' => $installationUuid,
            ],
            [
                'salesman_id' => $user->salesman?->id,
                'device_uuid' => $deviceUuid,
                'device_model' => $validated['device_model'] ?? null,
                'manufacturer' => $validated['manufacturer'] ?? null,
                'platform' => $platform,
                'os_version' => $osVersion,
                'android_version' => $validated['android_version'] ?? $osVersion,
                'app_version' => $appVersion,
                'push_token' => $validated['push_token'] ?? null,
                'is_active' => true,
                'registered_at' => $existing?->registered_at ?? now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
                'revoked_by' => null,
                'revocation_reason' => null,
            ]
        );

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Rotate only the token bound to this same registered device.
        $tokenName = 'mobile-'.$device->uuid;
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName, $permissions)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile_type' => $user->role,
            'salesman' => $user->salesman ? [
                'id' => $user->salesman->uuid,
                'employee_code' => $user->salesman->employee_code,
                'name' => $user->salesman->full_name,
            ] : null,
            'supervisor' => $user->supervisor ? [
                'id' => $user->supervisor->uuid,
                'employee_code' => $user->supervisor->employee_code,
                'name' => $user->supervisor->full_name,
            ] : null,
            'tenant' => [
                'id' => $user->tenant->uuid,
                'name' => $user->tenant->name,
                'timezone' => $user->tenant->timezone,
            ],
            'permissions' => $permissions,
            'device' => [
                'id' => $device->uuid,
                'device_uuid' => $device->device_uuid,
                'installation_uuid' => $device->installation_uuid,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
            ],
        ]);
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['tenant', 'salesman', 'supervisor']);
        /** @var Device|null $device */
        $device = $request->attributes->get('device');

        return ApiResponse::success([
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile_type' => $user->role,
            'salesman' => $user->salesman ? [
                'id' => $user->salesman->uuid,
                'employee_code' => $user->salesman->employee_code,
                'name' => $user->salesman->full_name,
            ] : null,
            'supervisor' => $user->supervisor ? [
                'id' => $user->supervisor->uuid,
                'employee_code' => $user->supervisor->employee_code,
                'name' => $user->supervisor->full_name,
            ] : null,
            'tenant' => [
                'id' => $user->tenant->uuid,
                'name' => $user->tenant->name,
                'timezone' => $user->tenant->timezone,
            ],
            'permissions' => $user->roles()
                ->with('permissions')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'device' => $device ? [
                'id' => $device->uuid,
                'device_uuid' => $device->device_uuid,
                'installation_uuid' => $device->installation_uuid,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(['logged_out' => true]);
    }
}
