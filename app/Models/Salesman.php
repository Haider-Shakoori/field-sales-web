<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Salesman extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(CurrentLocation::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(SalesmanAssignment::class)
            ->whereNull('effective_to')
            ->latestOfMany('effective_from');
    }
}
