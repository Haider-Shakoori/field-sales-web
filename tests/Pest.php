<?php

use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Seed the RBAC catalog (roles + permissions).
 */
function seedRbac(): void
{
    (new RbacSeeder)->run();
}

/**
 * Create a tenant (skipping factories' implicit state).
 */
function makeTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create($attributes);
}

/**
 * Create a user assigned a single role on the given tenant.
 */
function makeUser(Tenant $tenant, string $role = 'owner', array $attributes = []): User
{
    return withTenantContext($tenant, function () use ($tenant, $role, $attributes): User {
        $user = User::factory()->create(array_merge([
            'password' => 'Password123!',
        ], $attributes));

        $user->assignRole($role, $tenant->id);

        return $user;
    });
}

/**
 * Run a callback inside a tenant context without HTTP middleware,
 * restoring the previous context afterwards.
 */
function withTenantContext(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->withTenant($tenant, $callback);
}

/**
 * Run a callback inside the explicit platform context without HTTP middleware,
 * restoring the previous context afterwards.
 */
function withPlatformContext(callable $callback): mixed
{
    return app(TenantContext::class)->withSystemContext($callback);
}

/**
 * Create a salesman with a tenant-local mobile login.
 *
 * Returns the user, salesman profile, registered device, Sanctum token, and the
 * canonical device headers needed for Batch 7 attendance/GPS requests.
 *
 * @return array{user: User, salesman: Salesman, device: Device, token: string, headers: array<string, string>}
 */
function makeMobileSalesman(Tenant $tenant, array $userAttributes = []): array
{
    $salesman = null;

    $user = withTenantContext($tenant, function () use ($tenant, $userAttributes, &$salesman): User {
        $user = makeUser($tenant, 'salesman', array_merge([
            'email' => 'slm.'.Str::random(8).'@test.test',
        ], $userAttributes));

        $salesman = Salesman::factory()->forTenant($tenant->id)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'employee_code' => 'SLM-'.Str::random(6),
        ]);

        return $user;
    });

    $deviceUuid = 'device-'.Str::uuid();
    $installationUuid = 'install-'.Str::uuid();

    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123!',
        'device_uuid' => $deviceUuid,
        'device_model' => 'Pixel 8',
        'manufacturer' => 'Google',
        'android_version' => '14',
        'app_version' => '1.0.0',
    ], [
        'X-Installation-UUID' => $installationUuid,
        'X-App-Version' => '1.0.0',
    ])->assertOk();

    $device = withTenantContext($tenant, fn () => Device::query()
        ->where('device_uuid', $deviceUuid)
        ->firstOrFail());

    return [
        'user' => $user,
        'salesman' => $salesman,
        'device' => $device,
        'token' => $response->json('data.token'),
        'headers' => [
            'X-Device-UUID' => $deviceUuid,
            'X-Installation-UUID' => $installationUuid,
            'X-App-Version' => '1.0.0',
        ],
    ];
}
