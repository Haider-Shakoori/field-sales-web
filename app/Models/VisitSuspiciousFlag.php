<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitSuspiciousFlag extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo { return $this->belongsTo(CustomerVisit::class, 'visit_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
