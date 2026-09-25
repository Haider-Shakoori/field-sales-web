<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRunLine extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'basis_value' => 'decimal:4',
            'rate' => 'decimal:4',
            'commission_amount' => 'decimal:4',
            'evidence' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CommissionRun::class, 'commission_run_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }
}
