<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisitVoiceNote;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class VisitVoiceTranscriptionService
{
    public function __construct(private readonly AiPolicyService $policy) {}

    public function initialStatus(User $user): string
    {
        if (! (bool) config('ai.transcription_enabled', false)) {
            return 'disabled';
        }

        if (! $this->policy->customerDataEnabled($user)) {
            return 'blocked_policy';
        }

        if (
            $this->baseUrl() === ''
            || trim((string) config('ai.api_key')) === ''
            || trim((string) config('ai.transcription_model')) === ''
        ) {
            return 'not_configured';
        }

        return 'pending';
    }

    public function transcribe(VisitVoiceNote $note): void
    {
        $note->loadMissing('user');
        $status = $this->initialStatus($note->user);

        if ($status !== 'pending') {
            $note->update([
                'transcription_status' => $status,
                'transcription_error' => null,
            ]);

            return;
        }

        $note->update([
            'transcription_status' => 'processing',
            'transcription_error' => null,
        ]);

        try {
            $disk = Storage::disk($note->disk);
            $absolutePath = $disk->path($note->path);

            if (! is_file($absolutePath)) {
                throw new RuntimeException('Voice note file is missing from storage.');
            }

            $stream = fopen($absolutePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to read the voice note file.');
            }

            try {
                $payload = [
                    'model' => (string) config('ai.transcription_model'),
                    'response_format' => 'json',
                ];

                if (filled($note->language)) {
                    $payload['language'] = $note->language;
                }

                $response = $this->request()
                    ->attach(
                        'file',
                        $stream,
                        basename($absolutePath),
                        ['Content-Type' => $note->mime_type ?: 'audio/mp4'],
                    )
                    ->post($this->baseUrl().'/audio/transcriptions', $payload);
            } finally {
                fclose($stream);
            }

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Transcription provider returned HTTP '.$response->status().'.',
                );
            }

            $transcript = trim((string) $response->json('text'));

            if ($transcript === '') {
                throw new RuntimeException('Transcription provider returned an empty transcript.');
            }

            $note->update([
                'transcription_status' => 'completed',
                'transcript' => $transcript,
                'structured_notes' => $this->structureTranscript($transcript),
                'transcription_error' => null,
                'transcribed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $note->update([
                'transcription_status' => 'failed',
                'transcription_error' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            throw $exception;
        }
    }

    private function structureTranscript(string $transcript): array
    {
        $fallback = [
            'summary' => str($transcript)->limit(1200)->toString(),
            'customer_needs' => [],
            'commitments' => [],
            'follow_up' => null,
            'tags' => [],
        ];
        $model = trim((string) config('ai.model'));

        if ($model === '') {
            return $fallback;
        }

        try {
            $response = $this->request()->post($this->baseUrl().'/chat/completions', [
                'model' => $model,
                'temperature' => 0.1,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Convert a field-sales visit transcript into concise structured notes. Return only valid JSON with keys summary, customer_needs, commitments, follow_up, and tags. customer_needs, commitments, and tags must be arrays of short strings. follow_up must be a short string or null. Do not invent facts.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $transcript,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return $fallback;
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                return $fallback;
            }

            return [
                'summary' => trim((string) ($decoded['summary'] ?? $fallback['summary'])),
                'customer_needs' => $this->stringList($decoded['customer_needs'] ?? []),
                'commitments' => $this->stringList($decoded['commitments'] ?? []),
                'follow_up' => filled($decoded['follow_up'] ?? null)
                    ? trim((string) $decoded['follow_up'])
                    : null,
                'tags' => $this->stringList($decoded['tags'] ?? []),
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => str($item)->trim()->limit(180)->toString())
            ->take(12)
            ->values()
            ->all();
    }

    private function request(): PendingRequest
    {
        return Http::timeout(
            max(10, (int) config('ai.transcription_timeout_seconds', 45)),
        )
            ->acceptJson()
            ->withToken((string) config('ai.api_key'));
    }

    private function baseUrl(): string
    {
        $configured = rtrim(trim((string) config('ai.base_url')), '/');

        if ($configured !== '') {
            return $configured;
        }

        return match ((string) config('ai.provider')) {
            'groq' => 'https://api.groq.com/openai/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            default => '',
        };
    }
}
