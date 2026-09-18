<?php

use App\Support\Tracking\AttendanceTrackingSettings;

beforeEach(function (): void {
    seedRbac();
});

it('returns effective defaults to a registered mobile device', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.work_session_start_mode', 'manual')
        ->assertJsonPath('data.auto_end_session', false)
        ->assertJsonPath('data.gps_tracking_enabled', true)
        ->assertJsonPath('data.gps_moving_interval_seconds', 15)
        ->assertJsonPath('data.gps_stationary_interval_seconds', 60)
        ->assertJsonPath('data.gps_stale_after_minutes', 15)
        ->assertJsonPath('data.timezone', 'Asia/Kabul')
        ->assertJsonPath('data.privacy_policy_version', '1')
        ->assertJsonPath('data.updated_at', null);
});

it('returns customized policy values', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    withTenantContext($tenant, fn () => AttendanceTrackingSettings::update($tenant, [
        'work_session_start_mode' => 'automatic',
        'workday_start_time' => '20:00',
        'workday_end_time' => '04:00',
        'auto_end_session' => true,
        'gps_tracking_enabled' => false,
        'gps_moving_interval_seconds' => 30,
        'gps_stationary_interval_seconds' => 120,
        'gps_stale_after_minutes' => 45,
    ]));

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertOk()
        ->assertJsonPath('data.work_session_start_mode', 'automatic')
        ->assertJsonPath('data.workday_start_time', '20:00')
        ->assertJsonPath('data.workday_end_time', '04:00')
        ->assertJsonPath('data.auto_end_session', true)
        ->assertJsonPath('data.gps_tracking_enabled', false)
        ->assertJsonPath('data.gps_moving_interval_seconds', 30)
        ->assertJsonPath('data.gps_stationary_interval_seconds', 120)
        ->assertJsonPath('data.gps_stale_after_minutes', 45)
        ->assertJsonPath('data.timezone', 'Asia/Kabul')
        ->assertJsonPath('data.updated_at', fn ($value) => $value !== null);
});

it('requires a registered device for the settings endpoint', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'Device identification is required.');

    withTenantContext($tenant, fn () => $mobile['device']->fresh()->revoke());

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'This device has been revoked. Please reinstall the app.');
});

it('keeps settings tenant isolated for mobile clients', function (): void {
    $tenantA = makeTenant(['timezone' => 'Asia/Kabul']);
    $tenantB = makeTenant(['timezone' => 'Asia/Kabul']);

    $mobileA = makeMobileSalesman($tenantA);
    $mobileB = makeMobileSalesman($tenantB);

    withTenantContext($tenantA, fn () => AttendanceTrackingSettings::update($tenantA, [
        'work_session_start_mode' => 'automatic',
        'workday_start_time' => '06:00',
        'workday_end_time' => '14:00',
        'gps_stale_after_minutes' => 90,
    ]));

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertOk()
        ->assertJsonPath('data.work_session_start_mode', 'manual')
        ->assertJsonPath('data.workday_start_time', '08:00')
        ->assertJsonPath('data.gps_stale_after_minutes', 15);

    expect($mobileA['user']->tenant_id)->not->toBe($mobileB['user']->tenant_id);
});

it('lets a salesman read policy but never write it', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertOk();

    withTenantContext($tenant, function () use ($mobile): void {
        $this->actingAs($mobile['user'])
            ->get(route('settings.attendance-tracking.edit'))
            ->assertForbidden();

        $this->actingAs($mobile['user'])
            ->put(route('settings.attendance-tracking.update'), [
                'work_session_start_mode' => 'automatic',
            ])
            ->assertForbidden();
    });
});
