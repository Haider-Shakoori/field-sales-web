<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Expense extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['amount'=>'decimal:2','spent_at'=>'datetime','reviewed_at'=>'datetime']; }
