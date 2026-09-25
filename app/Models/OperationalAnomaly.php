<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalAnomaly extends Model
{
    use BelongsToTenant, HasUuid;

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const STATES = ['open', 'reviewed'];

    public const TYPES = ['collection', 'expense', 'order'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'occurred_at' => 'datetime',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
