<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
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
    // Issue #7711 (suite #7646) — table `approval_workflows` du schéma partagé
    // shared_tenants : company_id est l'unique frontière d'isolation.
    use BelongsToCompany;

    protected $fillable = [
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
