<?php

use App\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

it('renders the roles index, create, and audit pages', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->get(route('settings.roles.index'))
            ->assertOk()
            ->assertSee('Protected');

        $this->actingAs($owner)
            ->get(route('settings.roles.create'))
            ->assertOk()
            ->assertSee('New role');

        $this->actingAs($owner)
            ->get(route('audit.index'))
            ->assertOk();
    });
});

it('prevents a company admin from destroying a system role in use', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $system = Role::where('name', 'owner')->firstOrFail();

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->delete(route('settings.roles.destroy', $system))
        ->assertStatus(403));
});
