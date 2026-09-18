<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class Tenant extends Model { protected $guarded=[]; protected $casts=['settings'=>'array']; public function users(): HasMany{return $this->hasMany(User::class);} }
