<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeaturePlanMatrix extends Model
{
    protected $table = 'feature_plan_matrix';

    protected $fillable = [
        'feature_key',
        'plan',
        'enabled',
        'limit_value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }
}
