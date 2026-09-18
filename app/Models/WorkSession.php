<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSession extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_late_start' => 'boolean',
        'is_early_finish' => 'boolean',
        'corrections' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function salesman(): BelongsTo { return $this->belongsTo(Salesman::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
}
