<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Product extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['base_price'=>'decimal:4','is_active'=>'boolean']; }
