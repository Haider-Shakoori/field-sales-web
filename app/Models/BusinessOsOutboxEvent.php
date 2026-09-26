<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BusinessOsOutboxEvent extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
