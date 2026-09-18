<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class CustomerCategory extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['is_active'=>'boolean']; }
