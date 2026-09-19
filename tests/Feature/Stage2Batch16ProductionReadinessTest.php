<?php

namespace Tests\Feature;

use App\Support\ProductionReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Stage2Batch16ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_include_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    public function test_readiness_endpoint_is_public_and_checks_runtime_dependencies(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertExactJson(['status' => 'ready']);

        $this->assertNotContains(
            false,
            array_values(app(ProductionReadiness::class)->serviceChecks()),
            true,
        );
    }

    public function test_web_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => 'bruteforce@example.test',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'bruteforce@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_mobile_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'mobile-bruteforce@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile-bruteforce@example.test',
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_production_check_fails_closed_outside_production(): void
    {
        $exitCode = Artisan::call('field-sales:production-check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Production readiness checks failed.',
            Artisan::output(),
        );
    }
}
