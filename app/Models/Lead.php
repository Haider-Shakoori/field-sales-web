<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant, HasUuid;

    public const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    public const SOURCES = ['field', 'referral', 'website', 'phone', 'campaign', 'walk_in', 'other'];

    public const STAGE_PROBABILITIES = [
        'new' => 10,
        'contacted' => 25,
        'qualified' => 50,
        'proposal' => 65,
        'negotiation' => 80,
        'won' => 100,
        'lost' => 0,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:4',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'last_activity_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function assignedSalesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'assigned_salesman_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('occurred_at');
    }
}