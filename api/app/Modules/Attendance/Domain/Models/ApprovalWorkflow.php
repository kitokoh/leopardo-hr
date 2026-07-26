<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $model_type
 * @property array<int, mixed> $levels
 * @property float|null $auto_approve_below
 * @property int|null $escalation_hours
 * @property bool $active
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'model_type',
        'levels',
        'auto_approve_below',
        'escalation_hours',
        'active',
    ];

    protected $casts = [
        'levels' => 'array',
        'auto_approve_below' => 'float',
        'active' => 'boolean',
    ];
}

