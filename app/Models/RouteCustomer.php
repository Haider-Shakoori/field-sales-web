<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCustomer extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
