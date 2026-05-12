<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $feature_key
 * @property string $plan
 * @property bool $enabled
 * @property int $limit_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
