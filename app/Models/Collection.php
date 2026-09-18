<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Collection extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['amount'=>'decimal:2','collected_at'=>'datetime']; public function customer(){return $this->belongsTo(Customer::class);} public function order(){return $this->belongsTo(SalesOrder::class,'sales_order_id');} }
