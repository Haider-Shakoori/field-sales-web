<?php
namespace App\Models;
use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Relations\HasOne; use Illuminate\Foundation\Auth\User as Authenticatable; use Illuminate\Notifications\Notifiable; use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable { use HasApiTokens,HasFactory,Notifiable,BelongsToTenant; protected $guarded=[]; protected $hidden=['password','remember_token']; protected function casts(): array{return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean'];} public function salesman():HasOne{return $this->hasOne(Salesman::class);} }
