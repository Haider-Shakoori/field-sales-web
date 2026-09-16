<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'price_list_id',
        'product_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (PriceListItem $item) {
            AuditLogger::log('price_list_item.created', $item, [], [
                'price_list_id' => $item->price_list_id,
                'product_id' => $item->product_id,
                'price' => $item->price,
            ]);
        });

        static::updated(function (PriceListItem $item) {
            AuditLogger::changed('price_list_item.updated', $item, $item->getDirty());
        });

        static::deleted(function (PriceListItem $item) {
            AuditLogger::log('price_list_item.deleted', $item, [], [
                'price_list_id' => $item->price_list_id,
                'product_id' => $item->product_id,
                'price' => $item->price,
            ]);
        });
    }
}
