<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiConversationService
{
    public function listFor(User $user, int $limit = 30): Collection
    {
        return AiConversation::query()
            ->visibleTo($user)
            ->active()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function resolve(User $user, ?string $uuid): ?AiConversation
    {
        if (! $uuid) {
            return null;
        }

        return AiConversation::query()
            ->visibleTo($user)
            ->active()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(User $user, string $firstQuestion): AiConversation
    {
        return AiConversation::create([
            'user_id' => $user->id,
            'title' => $this->title($firstQuestion),
            'last_message_at' => now(),
        ]);
    }

    public function appendUser(
        AiConversation $conversation,
        User $user,
        string $content,
    ): AiMessage {
        $message = AiMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => trim($content),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    public function appendAssistant(
        AiConversation $conversation,
        array $result,
    ): AiMessage {
        $message = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => (string) $result['answer'],
            'source' => $result['source'] ?? null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'latency_ms' => $result['latency_ms'] ?? null,
            'prompt_tokens' => data_get($result, 'usage.prompt_tokens'),
            'completion_tokens' => data_get($result, 'usage.completion_tokens'),
            'meta' => array_filter([
                'provider_status' => $result['provider_status'] ?? null,
                'fallback_reason' => $result['fallback_reason'] ?? null,
                'tools_used' => $result['tools_used'] ?? null,
            ], fn ($value) => $value !== null),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    public function context(AiConversation $conversation, int $limit = 16): array
    {
        return $conversation->messages()
            ->reorder('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    public function messages(AiConversation $conversation): Collection
    {
        return $conversation->messages()
            ->orderBy('id')
            ->get();
    }

    public function archive(AiConversation $conversation): void
    {
        $conversation->forceFill(['archived_at' => now()])->save();
    }

    private function title(string $question): string
    {
        $clean = Str::squish(strip_tags($question));

        return Str::limit($clean !== '' ? $clean : 'New conversation', 80);
    }
}
