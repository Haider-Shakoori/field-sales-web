<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\CustomerCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCategory extends Model
{
    /** @use HasFactory<CustomerCategoryFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (CustomerCategory $category) {
            AuditLogger::log('customer_category.created', $category, [], [
                'name' => $category->name,
                'description' => $category->description,
            ]);
        });

        static::updated(function (CustomerCategory $category) {
            AuditLogger::changed('customer_category.updated', $category, $category->getDirty());
        });

        static::deleted(function (CustomerCategory $category) {
            AuditLogger::log('customer_category.deleted', $category, [], [
                'name' => $category->name,
            ]);
        });
    }
}
