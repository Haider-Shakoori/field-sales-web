<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class CustomerCallActivity extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['initiated_at'=>'datetime']; public function customer(){return $this->belongsTo(Customer::class);} public function visit(){return $this->belongsTo(CustomerVisit::class,'visit_id');} }
