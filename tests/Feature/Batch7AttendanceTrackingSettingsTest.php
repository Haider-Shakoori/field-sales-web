<?php

use App\Models\AuditLog;
use App\Support\Tracking\AttendanceTrackingSettings;

beforeEach(function (): void {
    seedRbac();
});

function batch7SettingsPayload(array $overrides = []): array
{
    return array_merge([
        'work_session_start_mode' => 'manual',
        'auto_end_session' => '0',
        'gps_tracking_enabled' => '1',
        'gps_moving_interval_seconds' => 15,
        'gps_stationary_interval_seconds' => 60,
        'gps_stale_after_minutes' => 15,
    ], $overrides);
}

it('resolves safe manual defaults when nothing is customized', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);

    withTenantContext($tenant, function (): void {
        $effective = AttendanceTrackingSettings::effective();

        expect($effective['values']['work_session_start_mode'])->toBe('manual')
            ->and($effective['values']['auto_end_session'])->toBeFalse()
            ->and($effective['values']['gps_tracking_enabled'])->toBeTrue()
            ->and($effective['values']['gps_moving_interval_seconds'])->toBe(15)
            ->and($effective['values']['gps_stationary_interval_seconds'])->toBe(60)
            ->and($effective['values']['gps_stale_after_minutes'])->toBe(15)
            ->and($effective['updated_at'])->toBeNull();
    });
});

it('shows the settings page to authorized admins', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->get(route('settings.attendance-tracking.edit'))
            ->assertOk()
            ->assertSee('Work session start mode')
            ->assertSee('GPS tracking enabled')
            ->assertSee('Save settings');
    });
});

it('denies the settings page to roles without settings permission', function (): void {
    $tenant = makeTenant();
    $salesmanRole = makeUser($tenant, 'salesman');
    $supervisor = makeUser($tenant, 'supervisor');

    withTenantContext($tenant, function () use ($salesmanRole, $supervisor): void {
        $this->actingAs($salesmanRole)->get(route('settings.attendance-tracking.edit'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('settings.attendance-tracking.edit'))->assertForbidden();
    });
});

it('renders the settings page read-only for viewers without manage permission', function (): void {
    $tenant = makeTenant();
    $auditor = makeUser($tenant, 'auditor');

    withTenantContext($tenant, function () use ($auditor): void {
        $this->actingAs($auditor)
            ->get(route('settings.attendance-tracking.edit'))
            ->assertOk()
            ->assertSee('read-only access')
            ->assertDontSee('Save settings');

        $this->actingAs($auditor)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload())
            ->assertForbidden();
    });
});

it('saves manual settings and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'workday_start_time' => '09:00',
                'workday_end_time' => '18:00',
                'auto_end_session' => '1',
                'gps_moving_interval_seconds' => 30,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(AttendanceTrackingSettings::effective()['values']['work_session_start_mode'])->toBe('manual')
            ->and($tenant->setting('tracking.workday_start_time'))->toBe('09:00')
            ->and($tenant->setting('tracking.auto_end_session'))->toBeTrue()
            ->and($tenant->setting('tracking.gps_moving_interval_seconds'))->toBe(30);

        $audit = AuditLog::query()->where('event', 'tracking.settings.updated')->firstOrFail();

        expect($audit->new_values)->toHaveKey('workday_start_time')
            ->and($audit->new_values['auto_end_session'])->toBeTrue();
    });
});

it('accepts automatic mode with a normal workday window', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'work_session_start_mode' => 'automatic',
                'workday_start_time' => '08:00',
                'workday_end_time' => '17:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $effective = AttendanceTrackingSettings::effective()['values'];

        expect($effective['work_session_start_mode'])->toBe('automatic')
            ->and($effective['workday_start_time'])->toBe('08:00')
            ->and($effective['workday_end_time'])->toBe('17:00');
    });
});

it('accepts an overnight automatic window', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'work_session_start_mode' => 'automatic',
                'workday_start_time' => '20:00',
                'workday_end_time' => '04:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($tenant->setting('tracking.workday_start_time'))->toBe('20:00')
            ->and($tenant->setting('tracking.workday_end_time'))->toBe('04:00');
    });
});

it('requires workday times when automatic mode is selected', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'work_session_start_mode' => 'automatic',
            ]))
            ->assertSessionHasErrors(['workday_start_time', 'workday_end_time']);
    });
});

it('validates interval and stale thresholds', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'gps_moving_interval_seconds' => 5,
            ]))
            ->assertSessionHasErrors('gps_moving_interval_seconds');

        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'gps_moving_interval_seconds' => 3601,
            ]))
            ->assertSessionHasErrors('gps_moving_interval_seconds');

        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'gps_moving_interval_seconds' => 120,
                'gps_stationary_interval_seconds' => 60,
            ]))
            ->assertSessionHasErrors('gps_stationary_interval_seconds');

        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'gps_stale_after_minutes' => 0,
            ]))
            ->assertSessionHasErrors('gps_stale_after_minutes');

        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'gps_stale_after_minutes' => 241,
            ]))
            ->assertSessionHasErrors('gps_stale_after_minutes');
    });
});

it('rejects malformed workday times', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'work_session_start_mode' => 'automatic',
                'workday_start_time' => '8am',
                'workday_end_time' => '17:00',
            ]))
            ->assertSessionHasErrors('workday_start_time');
    });
});

it('keeps tracking settings tenant isolated', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');

    withTenantContext($tenantA, function () use ($ownerA): void {
        $this->actingAs($ownerA)
            ->put(route('settings.attendance-tracking.update'), batch7SettingsPayload([
                'work_session_start_mode' => 'automatic',
                'workday_start_time' => '07:00',
                'workday_end_time' => '16:00',
                'gps_stale_after_minutes' => 45,
            ]))
            ->assertRedirect();
    });

    withTenantContext($tenantB, function (): void {
        $effective = AttendanceTrackingSettings::effective();

        expect($effective['values']['work_session_start_mode'])->toBe('manual')
            ->and($effective['values']['workday_start_time'])->toBe('08:00')
            ->and($effective['values']['gps_stale_after_minutes'])->toBe(15);
    });
});
