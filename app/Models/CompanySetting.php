<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model;
class CompanySetting extends Model { use BelongsToTenant; protected $guarded=[]; public $timestamps=true; }
