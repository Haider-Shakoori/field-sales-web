<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Target extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['period_start'=>'date','period_end'=>'date','target_value'=>'decimal:2','weight'=>'decimal:2','is_active'=>'boolean']; }
