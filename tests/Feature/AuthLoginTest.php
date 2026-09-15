<?php

use App\Models\User;

beforeEach(function (): void {
    seedRbac();
});

it('shows the login form', function (): void {
    $this->get(route('login'))->assertOk();
});

it('authenticates a company user with valid credentials', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'owner', ['email' => 'owner@test.test']);

    $this->post(route('login'), [
        'email' => 'owner@test.test',
        'password' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'owner@test.test']);

    $this->post(route('login'), [
        'email' => 'owner@test.test',
        'password' => 'wrong-password',
    ])->assertRedirect()->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out an authenticated user and invalidates the session', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'owner');

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('throttles repeated failed login attempts', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'owner@test.test']);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login'), [
            'email' => 'owner@test.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post(route('login'), [
        'email' => 'owner@test.test',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    expect(session('errors')->get('email')[0])
        ->toContain('Too many login attempts');
});

it('does not let a guest access the dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('registers the last_login_at timestamp on successful login', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'owner');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    withTenantContext($tenant, function () use ($user): void {
        expect(User::find($user->id)->last_login_at)->not->toBeNull();
    });
});

it('stores a session user_id keyed to the user primary key', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'owner');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(auth()->id())->toBe((int) $user->id);
});
