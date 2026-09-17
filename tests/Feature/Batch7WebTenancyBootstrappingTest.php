<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    seedRbac();

    // NOTE: runtime-registered routes dispatch fine by URL but their ->name()
    // lookup is not registered in the test bootstrapped router, so tests must
    // hit the literal path below, never '/probe/context'. The web middleware
    // group must be attached explicitly, otherwise the tenancy bootstraps
    // (BootstrapTenantForWebAuth, InitializeTenancy) never run.
    Route::middleware('web')->get('probe/context', function () {
        $state = TenantContext::currentState();

        return response()->json([
            'state' => $state->value,
            'tenant_id' => TenantContext::currentId(),
        ]);
    });
});

/**
 * Create a platform super administrator (tenant_id null, super_admin role).
 */
function makePlatformAdminUser(string $email = 'super.web.platform@test.test'): User
{
    return withPlatformContext(function () use ($email): User {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'email' => $email,
            'is_active' => true,
        ]);

        $admin->assignRole('super_admin', null);

        return $admin;
    });
}

/**
 * Send a real session login (never actingAs) and read back the session cookie.
 *
 * @return array{0: string, 1: string} [cookie name, cookie value]
 */
function webLogin(string $email): array
{
    $response = test()->post(route('login'), [
        'email' => $email,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect(route('dashboard'));

    return webSessionCookie($response);
}

/**
 * Read the [name, value] pair of the session cookie from a response.
 *
 * @return array{0: string, 1: string}
 */
function webSessionCookie(TestResponse $response): array
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)
        ->not->toBeNull('The session cookie was not attached to the response.');

    return [$cookie->getName(), $cookie->getValue()];
}

/*
 * Case A — fresh/incognito guest.
 */

it('serves /login to a fresh guest without throwing a tenant context error', function (): void {
    $this->get(route('login'))->assertOk();
});

it('keeps the tenant context uninitialized for a fresh guest request', function (): void {
    $this->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Uninitialized->value)
        ->assertJsonPath('tenant_id', null);
});

it('redirects a guest away from /dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

/*
 * Case B — /login with an existing authenticated browser session.
 */

it('redirects an authenticated session away from /login without a tenant error', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'web.redirect@test.test']);

    [$name, $value] = webLogin('web.redirect@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

/*
 * Case C — /login with a stale session cookie (references a deleted user).
 */

it('serves /login with a stale session cookie without a tenant error', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner', ['email' => 'web.stale@test.test']);

    [$name, $value] = webLogin('web.stale@test.test');

    $owner->forceDelete();

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get(route('login'))
        ->assertOk();
});

/*
 * Real-session regression: the user must be restored from the session
 * provider (never pre-loaded with actingAs).
 */

it('restores the authenticated user from the session and bootstraps their tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner', ['email' => 'web.restore@test.test']);

    [$name, $value] = webLogin('web.restore@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($owner->name);
});

it('executes company controllers under the concrete tenant context, never the bootstrap scope', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'web.tenantstate@test.test']);

    [$name, $value] = webLogin('web.tenantstate@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Tenant->value)
        ->assertJsonPath('tenant_id', $tenant->id);
});

/*
 * Platform admin.
 */

it('bootstraps a platform super admin into the authorized platform context', function (): void {
    $admin = makePlatformAdminUser('web.superadmin@test.test');

    [$name, $value] = webLogin('web.superadmin@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Platform->value)
        ->assertJsonPath('tenant_id', null);

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($admin->name);
});

/*
 * Tenant leak regression — sequential web sessions must never share context.
 */

it('does not leak tenant context between two sequential web sessions', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    makeUser($tenantA, 'owner', ['email' => 'web.leak.a@test.test']);
    makeUser($tenantB, 'owner', ['email' => 'web.leak.b@test.test']);

    [$nameA, $valueA] = webLogin('web.leak.a@test.test');

    Auth::forgetGuards();

    $this->withCookie($nameA, $valueA)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Tenant->value)
        ->assertJsonPath('tenant_id', $tenantA->id);

    Auth::forgetGuards();

    $this->withCookie($nameA, $valueA)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    [$nameB, $valueB] = webLogin('web.leak.b@test.test');

    Auth::forgetGuards();

    $this->withCookie($nameB, $valueB)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Tenant->value)
        ->assertJsonPath('tenant_id', $tenantB->id);
});

it('does not leak tenant context between two sequential web sessions in reverse order', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    makeUser($tenantA, 'owner', ['email' => 'web.leak.rev.a@test.test']);
    makeUser($tenantB, 'owner', ['email' => 'web.leak.rev.b@test.test']);

    [$nameB, $valueB] = webLogin('web.leak.rev.b@test.test');

    Auth::forgetGuards();

    $this->withCookie($nameB, $valueB)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('tenant_id', $tenantB->id);

    Auth::forgetGuards();

    $this->withCookie($nameB, $valueB)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    [$nameA, $valueA] = webLogin('web.leak.rev.a@test.test');

    Auth::forgetGuards();

    $this->withCookie($nameA, $valueA)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Tenant->value)
        ->assertJsonPath('tenant_id', $tenantA->id);
});

it('returns to /login as a guest after logout with no tenant context errors', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'web.logout@test.test']);

    [$name, $value] = webLogin('web.logout@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get(route('login'))
        ->assertOk();
});

/*
 * The disputed primitive: withSystemScope is the POST /login path guard, and an
 * authenticated company user must not be able to activate it by swapping in a
 * different user on the same worker.
 */

it('does not let the web bootstrap leak a temporary platform scope into the request', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'web.noscope@test.test']);

    [$name, $value] = webLogin('web.noscope@test.test');

    Auth::forgetGuards();

    $this->withCookie($name, $value)
        ->get('/probe/context')
        ->assertOk()
        ->assertJsonPath('state', TenantContextState::Tenant->value)
        ->assertJsonPath('tenant_id', $tenant->id);
});
