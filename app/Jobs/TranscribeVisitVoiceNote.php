<?php

namespace App\Jobs;

use App\Models\VisitVoiceNote;
use App\Services\VisitVoiceTranscriptionService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscribeVisitVoiceNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $voiceNoteId,
    ) {}

    public function handle(
        TenantContext $context,
        VisitVoiceTranscriptionService $transcription,
    ): void {
        $context->withTenant($this->tenantId, function () use ($transcription): void {
            $note = VisitVoiceNote::find($this->voiceNoteId);

            if (! $note || $note->transcription_status === 'completed') {
                return;
            }

            $transcription->transcribe($note);
        });
    }
}
