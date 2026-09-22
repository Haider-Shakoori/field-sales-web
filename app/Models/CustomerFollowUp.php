<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFollowUp extends Model
{
    use BelongsToTenant, HasUuid;

    public const TYPES = ['call', 'visit', 'payment', 'order', 'other'];
    public const PRIORITIES = ['low', 'normal', 'high'];
    public const STATUSES = ['pending', 'completed', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function assignedSalesman(): BelongsTo { return $this->belongsTo(Salesman::class, 'assigned_salesman_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
