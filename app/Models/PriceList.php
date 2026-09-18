<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class PriceList extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; public function items(){return $this->hasMany(PriceListItem::class);} }
