<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Automatisation CRM (règle event/conditions/actions, issue #5728).
 *
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string $trigger_event
 * @property array<int, array<string, mixed>>|null $conditions
 * @property array<int, array<string, mixed>> $actions
 * @property string $status
 * @property int $version
 * @property string|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmAutomation extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_automations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<CrmAutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(CrmAutomationRun::class, 'automation_id');
    }
}
