<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class OnboardingStep extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'step_key',
        'title',
        'description',
        'status',
        'completed_at',
        'completed_by',
        'order',
        'required',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'required' => 'boolean',
            'order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
