<?php
namespace App\Models;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PriceListItem extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['price'=>'decimal:4','min_quantity'=>'decimal:4']; public function product(){return $this->belongsTo(Product::class);} public function priceList(){return $this->belongsTo(PriceList::class);} }
