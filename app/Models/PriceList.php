<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'is_default',
        'is_active',
        'uuid',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'price_list_items')
            ->withTimestamps()
            ->withPivot('price');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (PriceList $priceList) {
            AuditLogger::log('price_list.created', $priceList, [], [
                'name' => $priceList->name,
                'is_default' => $priceList->is_default,
            ]);
        });

        static::updated(function (PriceList $priceList) {
            AuditLogger::changed('price_list.updated', $priceList, $priceList->getDirty());

            if ($priceList->isDirty('is_default') && $priceList->is_default) {
                AuditLogger::log('price_list.default_changed', $priceList, [
                    'is_default' => $priceList->getOriginal('is_default'),
                ], [
                    'is_default' => true,
                    'name' => $priceList->name,
                ]);
            }

            if ($priceList->isDirty('is_active')) {
                AuditLogger::log($priceList->is_active ? 'price_list.activated' : 'price_list.deactivated', $priceList, [
                    'is_active' => $priceList->getOriginal('is_active'),
                ], [
                    'is_active' => $priceList->is_active,
                    'name' => $priceList->name,
                ]);
            }
        });
    }
}
