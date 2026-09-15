<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupervisorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supervisor extends Model
{
    /** @use HasFactory<SupervisorFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
