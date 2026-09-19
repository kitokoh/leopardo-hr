<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Interrupteur d'urgence des automatisations CRM par tenant (issue #5728).
 *
 * @property string $company_id
 * @property bool $enabled
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmAutomationState extends Model
{
    protected $table = 'crm_automation_states';

    public $incrementing = false;

    protected $primaryKey = 'company_id';

    protected $keyType = 'string';

    public $timestamps = false;

    // #7646 — allowlist explicite. `company_id` reste volontairement
    // assignable ICI (exception justifiée) : c'est la clé primaire de cette
    // table sans BelongsToCompany, écrite exclusivement côté serveur via
    // AutomationEngine::setEmergencyStop() avec currentCompany()->id —
    // jamais depuis un payload utilisateur.
    protected $fillable = [
        'company_id',
        'enabled',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
