<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request, TenantContext $context)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant' => ['nullable', 'string', 'max:191'],
            'device_uuid' => ['required', 'string', 'max:191'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'manufacturer' => ['nullable', 'string', 'max:191'],
            'android_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'push_token' => ['nullable', 'string', 'max:1000'],
        ]);

        $installationUuid = $request->header('X-Installation-UUID');

        if (! $installationUuid) {
            return ApiResponse::error(
                'X-Installation-UUID is required.',
                422,
                null,
                'INSTALLATION_UUID_REQUIRED'
            );
        }

        $matches = $context->withAuthenticationBootstrapScope(function () use ($validated) {
            return User::with(['tenant', 'salesman'])
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

        if (! $user->salesman) {
            return ApiResponse::error(
                'No salesman profile is linked to this user.',
                422,
                null,
                'SALESMAN_REQUIRED'
            );
        }

        $context->initializeTenant((int) $user->tenant_id);

        $existing = Device::where('user_id', $user->id)
            ->where('installation_uuid', $installationUuid)
            ->first();

        if ($existing?->revoked_at) {
            return ApiResponse::error(
                'This device has been revoked.',
                403,
                null,
                'DEVICE_REVOKED'
            );
        }

        $other = Device::where('salesman_id', $user->salesman->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->first();

        if ($other) {
            return ApiResponse::error(
                'Only one active device is allowed for this salesman.',
                422,
                null,
                'DEVICE_LIMIT_REACHED'
            );
        }

        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'installation_uuid' => $installationUuid,
            ],
            [
                'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                'salesman_id' => $user->salesman->id,
                'device_uuid' => $validated['device_uuid'],
                'device_model' => $validated['device_model'] ?? null,
                'manufacturer' => $validated['manufacturer'] ?? null,
                'android_version' => $validated['android_version'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'push_token' => $validated['push_token'] ?? null,
                'is_active' => true,
                'registered_at' => $existing?->registered_at ?? now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        $user->tokens()->where('name', 'mobile-'.$device->uuid)->delete();
        $token = $user->createToken('mobile-'.$device->uuid)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tenant' => [
                'id' => $user->tenant->uuid,
                'name' => $user->tenant->name,
                'timezone' => $user->tenant->timezone,
            ],
            'permissions' => [
                'attendance.view',
                'attendance.manage',
                'gps.upload',
            ],
            'device' => [
                'id' => $device->uuid,
                'device_uuid' => $device->device_uuid,
                'installation_uuid' => $device->installation_uuid,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(['logged_out' => true]);
    }
}
