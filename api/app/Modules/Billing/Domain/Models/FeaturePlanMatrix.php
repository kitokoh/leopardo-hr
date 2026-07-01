<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $feature_key
 * @property string $plan
 * @property bool $enabled
 * @property int $limit_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
