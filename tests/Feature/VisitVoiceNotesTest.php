<?php

namespace Tests\Feature;

use App\Jobs\TranscribeVisitVoiceNote;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitVoiceNote;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitVoiceNotesTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    public function test_voice_note_is_private_idempotent_and_listed_without_external_transcription(): void
    {
        Storage::fake('local');
        Queue::fake();
        $fixture = $this->fixture();
        $uuid = (string) Str::uuid();

        $this->upload($fixture['visit'], $uuid)
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.duration_seconds', 42)
            ->assertJsonPath('data.transcription_status', 'disabled')
            ->assertJsonPath('data.transcript', null);

        $note = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => VisitVoiceNote::where('uuid', $uuid)->firstOrFail(),
        );

        Storage::disk('local')->assertExists($note->path);
        Queue::assertNothingPushed();

        $this->upload($fixture['visit'], $uuid)
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->assertDatabaseCount('visit_voice_notes', 1);

        $this->getJson(
            '/api/v1/visits/'.$fixture['visit']->uuid.'/voice-notes',
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uuid);
    }

    public function test_transcription_is_blocked_when_customer_data_ai_is_not_allowed(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('ai.transcription_enabled', true);
        config()->set('ai.enabled', true);
        config()->set('ai.allow_customer_data', false);

        $fixture = $this->fixture();

        $this->upload($fixture['visit'], (string) Str::uuid())
            ->assertCreated()
            ->assertJsonPath('data.transcription_status', 'blocked_policy');

        Queue::assertNothingPushed();
    }

    public function test_transcription_is_queued_only_after_explicit_ai_privacy_gates_are_enabled(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('ai.transcription_enabled', true);
        config()->set('ai.enabled', true);
        config()->set('ai.allow_customer_data', true);

        $fixture = $this->fixture();

        $this->upload($fixture['visit'], (string) Str::uuid())
            ->assertCreated()
            ->assertJsonPath('data.transcription_status', 'queued');

        Queue::assertPushed(
            TranscribeVisitVoiceNote::class,
            fn (TranscribeVisitVoiceNote $job) => $job->tenantId === $fixture['tenant']->id,
        );
    }

    private function upload(CustomerVisit $visit, string $uuid)
    {
        return $this->withHeaders($this->headers())->post(
            '/api/v1/visits/'.$visit->uuid.'/voice-notes',
            [
                'client_uuid' => $uuid,
                'duration_seconds' => 42,
                'recorded_at' => now()->subMinute()->toISOString(),
                'audio' => UploadedFile::fake()->create(
                    'visit-note.m4a',
                    256,
                    'audio/mp4',
                ),
            ],
        );
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Voice Tenant',
            'slug' => 'voice-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Voice Salesman',
                'email' => 'voice-salesman@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesman = Salesman::create([
                'user_id' => $user->id,
                'employee_code' => 'VOICE-1',
                'first_name' => 'Voice',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);
            $device = Device::create([
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'voice-device',
                'installation_uuid' => 'voice-install',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'code' => 'VOICE-C1',
                'name' => 'Voice Customer',
                'latitude' => 34.5,
                'longitude' => 69.2,
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);
            $visit = CustomerVisit::create([
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'is_planned' => false,
                'status' => 'completed',
                'outcome' => 'order_placed',
                'checked_in_at' => now()->subMinutes(20),
                'checked_out_at' => now()->subMinutes(10),
                'checkin_latitude' => 34.5,
                'checkin_longitude' => 69.2,
                'checkin_accuracy' => 5,
                'checkout_latitude' => 34.5,
                'checkout_longitude' => 69.2,
                'checkout_accuracy' => 5,
                'duration_seconds' => 600,
            ]);

            $this->token = $user->createToken('mobile-'.$device->uuid)->plainTextToken;

            return compact('tenant', 'user', 'salesman', 'device', 'customer', 'visit');
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'voice-device',
            'X-Installation-UUID' => 'voice-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
            'Accept' => 'application/json',
        ];
    }
}
