<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessOsSyncState extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
