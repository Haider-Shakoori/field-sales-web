<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Tenant extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo_url',
        'timezone',
        'default_currency',
        'locale',
        'settings',
        'subscription_status',
        'trial_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function isSubscribed(): bool
    {
        return in_array($this->subscription_status, ['active', 'trial'], true);
    }

    /**
     * Read a company setting value.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->companySettings()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Upsert a company setting value.
     */
    public function setSetting(string $key, mixed $value): CompanySetting
    {
        return CompanySetting::updateOrCreate(
            ['tenant_id' => $this->id, 'key' => $key],
            ['value' => $value],
        );
    }

    public static function booted(): void
    {
        static::deleting(function (Tenant $tenant): void {
            DB::transaction(fn () => $tenant->users()->delete());
        });
    }
}
