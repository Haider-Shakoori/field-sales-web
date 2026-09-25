<?php

namespace App\Services;

use App\Models\VisitVoiceNote;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class VisitVoiceTranscriptionService
{
    public function __construct(private readonly AiPolicyService $policy) {}

    public function transcribe(VisitVoiceNote $note): void
    {
        $note->loadMissing(['user.tenant', 'visit.customer']);

        if (! (bool) config('ai.transcription_enabled', false)) {
            $note->update([
                'transcription_status' => 'disabled',
                'transcription_error' => null,
            ]);

            return;
        }

        if (! $this->policy->customerDataEnabled($note->user)) {
            $note->update([
                'transcription_status' => 'blocked_policy',
                'transcription_error' => null,
            ]);

            return;
        }

        $baseUrl = $this->baseUrl();
        $apiKey = trim((string) config('ai.api_key'));
        $model = trim((string) config('ai.transcription_model', 'whisper-large-v3-turbo'));

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            $this->fail($note, 'Voice transcription provider is not fully configured.');

            return;
        }

        $note->update([
            'transcription_status' => 'processing',
            'transcription_provider' => (string) config('ai.provider', 'generic'),
            'transcription_model' => $model,
            'transcription_error' => null,
        ]);

        try {
            $disk = Storage::disk($note->disk);

            if (! $disk->exists($note->path)) {
                throw new RuntimeException('Voice-note audio file is missing.');
            }

            $response = Http::timeout(
                max(15, (int) config('ai.transcription_timeout_seconds', 60)),
            )
                ->acceptJson()
                ->withToken($apiKey)
                ->attach(
                    'file',
                    $disk->get($note->path),
                    basename($note->path),
                    ['Content-Type' => $note->mime_type ?: 'audio/mp4'],
                )
                ->post($baseUrl.'/audio/transcriptions', array_filter([
                    'model' => $model,
                    'response_format' => 'json',
                    'language' => trim((string) config('ai.transcription_language')) ?: null,
                ], fn ($value) => $value !== null));

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Voice transcription provider returned HTTP '.$response->status().'.',
                );
            }

            $transcript = trim((string) $response->json('text'));

            if ($transcript === '') {
                throw new RuntimeException('Voice transcription provider returned an empty transcript.');
            }

            $note->update([
                'transcription_status' => 'completed',
                'transcript' => $transcript,
                'structured_notes' => $this->structure($note, $transcript, $baseUrl, $apiKey),
                'transcription_error' => null,
                'transcribed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($note, $exception->getMessage());
        }
    }

    private function structure(
        VisitVoiceNote $note,
        string $transcript,
        string $baseUrl,
        string $apiKey,
    ): array {
        $chatModel = trim((string) config('ai.model'));

        if ($chatModel === '') {
            return $this->fallbackStructure($transcript);
        }

        try {
            $response = Http::timeout(
                max(10, min(45, (int) config('ai.timeout_seconds', 20))),
            )
                ->acceptJson()
                ->withToken($apiKey)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $chatModel,
                    'temperature' => 0,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode(' ', [
                                'Convert a field-sales visit transcript into concise structured notes.',
                                'Return valid JSON only with keys summary, customer_requests, next_actions, objections, product_mentions.',
                                'summary must be a string and every other key must be an array of short strings.',
                                'Do not invent facts that are not in the transcript.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Customer: '.($note->visit?->customer?->name ?? 'Unknown')."\nTranscript:\n".$transcript,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return $this->fallbackStructure($transcript);
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $content = preg_replace('/^\x60\x60\x60(?:json)?\s*|\s*\x60\x60\x60$/i', '', $content) ?? $content;
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                return $this->fallbackStructure($transcript);
            }

            return [
                'summary' => trim((string) ($decoded['summary'] ?? '')),
                'customer_requests' => $this->strings($decoded['customer_requests'] ?? []),
                'next_actions' => $this->strings($decoded['next_actions'] ?? []),
                'objections' => $this->strings($decoded['objections'] ?? []),
                'product_mentions' => $this->strings($decoded['product_mentions'] ?? []),
            ];
        } catch (Throwable) {
            return $this->fallbackStructure($transcript);
        }
    }

    private function fallbackStructure(string $transcript): array
    {
        return [
            'summary' => $transcript,
            'customer_requests' => [],
            'next_actions' => [],
            'objections' => [],
            'product_mentions' => [],
        ];
    }

    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item) => trim((string) $item))
            ->take(20)
            ->values()
            ->all();
    }

    private function fail(VisitVoiceNote $note, string $message): void
    {
        $note->update([
            'transcription_status' => 'failed',
            'transcription_error' => str($message)->limit(1000)->toString(),
        ]);
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
