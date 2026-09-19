<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Exécution d'une automatisation CRM (issue #5728).
 *
 * `run_key` = hash déterministe (automation_id|event|entity_type|entity_id) :
 * un même événement sur la même entité ne s'exécute qu'une fois (idempotence
 * + anti-boucle). `dry_run` = simulation sans effet de bord.
 *
 * @property string $id
 * @property string $company_id
 * @property string $automation_id
 * @property string $trigger_event
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string $run_key
 * @property array<int, array<string, mixed>>|null $conditions_snapshot
 * @property array<int, array<string, mixed>>|null $actions_snapshot
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property bool $dry_run
 * @property string|null $error
 * @property Carbon|null $ran_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmAutomationRun extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_automation_runs';

    // #7646 — allowlist explicite SANS `company_id` : le tenant est posé par
    // le hook creating de BelongsToCompany, jamais par mass assignment.
    protected $fillable = [
        'automation_id',
        'trigger_event',
        'entity_type',
        'entity_id',
        'run_key',
        'conditions_snapshot',
        'actions_snapshot',
        'status',
        'attempts',
        'max_attempts',
        'dry_run',
        'error',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions_snapshot' => 'array',
            'actions_snapshot' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'dry_run' => 'boolean',
            'ran_at' => 'datetime',
        ];
    }
}
