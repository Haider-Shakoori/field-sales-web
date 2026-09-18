<?php
namespace App\Models;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class SalesOrderItem extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['quantity'=>'decimal:4','unit_price'=>'decimal:4','discount_amount'=>'decimal:2','line_total'=>'decimal:2']; public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} public function product(){return $this->belongsTo(Product::class);} }
