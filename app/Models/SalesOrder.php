<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class SalesOrder extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['ordered_at'=>'datetime','subtotal'=>'decimal:2','discount_amount'=>'decimal:2','total_amount'=>'decimal:2','cash_amount'=>'decimal:2','credit_amount'=>'decimal:2']; public function items(){return $this->hasMany(SalesOrderItem::class);} public function customer(){return $this->belongsTo(Customer::class);} public function visit(){return $this->belongsTo(CustomerVisit::class,'visit_id');} }
