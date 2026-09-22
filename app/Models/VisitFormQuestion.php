<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitFormQuestion extends Model
{
    use BelongsToTenant, HasUuid;

    public const TYPES = [
        'text',
        'textarea',
        'number',
        'yes_no',
        'single_choice',
        'multi_choice',
        'date',
        'photo',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'template_version' => 'integer',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VisitFormTemplate::class, 'template_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(VisitFormAnswer::class, 'question_id');
    }
}
