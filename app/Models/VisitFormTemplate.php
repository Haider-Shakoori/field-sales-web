<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitFormTemplate extends Model
{
    use BelongsToTenant, HasUuid;

    public const SCOPE_TYPES = ['all', 'branch', 'territory', 'route'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'required_on_checkout' => 'boolean',
            'is_active' => 'boolean',
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

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(VisitFormQuestion::class, 'template_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(VisitFormSubmission::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
