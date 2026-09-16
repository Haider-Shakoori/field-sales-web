<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    public const UNITS = ['piece', 'box', 'case', 'kg', 'litre'];

    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'unit',
        'price',
        'is_active',
        'category',
        'uuid',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class, 'price_list_items')
            ->withTimestamps()
            ->withPivot('price');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (Product $product) {
            AuditLogger::log('product.created', $product, [], [
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category,
                'unit' => $product->unit,
                'price' => $product->price,
            ]);
        });

        static::updated(function (Product $product) {
            AuditLogger::changed('product.updated', $product, $product->getDirty());

            if ($product->isDirty('is_active')) {
                AuditLogger::log($product->is_active ? 'product.activated' : 'product.deactivated', $product, [
                    'is_active' => $product->getOriginal('is_active'),
                ], [
                    'is_active' => $product->is_active,
                    'sku' => $product->sku,
                    'name' => $product->name,
                ]);
            }
        });

        static::deleted(function (Product $product) {
            AuditLogger::log('product.deleted', $product, [], [
                'sku' => $product->sku,
                'name' => $product->name,
            ]);
        });
    }
}
