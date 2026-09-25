<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitVoiceNote extends Model
{
    use BelongsToTenant, HasUuid;

    public const TRANSCRIPTION_STATUSES = [
        'disabled',
        'blocked_policy',
        'queued',
        'processing',
        'completed',
        'failed',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'structured_notes' => 'array',
            'transcribed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CustomerVisit::class, 'visit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
