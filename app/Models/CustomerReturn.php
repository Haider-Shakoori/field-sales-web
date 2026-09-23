<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerReturn extends Model
{
    use BelongsToTenant, HasUuid;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const CONDITIONS = ['sellable', 'damaged'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CustomerVisit::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerReturnItem::class)->orderBy('id');
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
