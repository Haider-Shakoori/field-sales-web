<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('uuid')) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
