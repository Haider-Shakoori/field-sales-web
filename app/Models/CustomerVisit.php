<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerVisit extends Model
{
    use BelongsToTenant, HasUuid;

    public const OUTCOMES = [
        'order_placed',
        'collection_made',
        'complaint_received',
        'no_stock_needed',
        'shop_closed',
        'customer_unavailable',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_planned' => 'boolean',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'checkin_within_geofence' => 'boolean',
            'checkout_within_geofence' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'visit_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VisitPhoto::class, 'visit_id');
    }

    public function suspiciousFlags(): HasMany
    {
        return $this->hasMany(VisitSuspiciousFlag::class, 'visit_id');
    }
}
