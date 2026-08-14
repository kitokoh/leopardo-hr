<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issue #1813 — Audit trail immuable des modifications de taux légaux.
 *
 * Table append-only : aucun UPDATE/DELETE possible (REVOKE au niveau base +
 * garde-fou applicatif via __update/__delete). Chaque transition du workflow
 * de validation (création, soumission, approbation, rejet, remplacement)
 * écrit une entrée avec l'état avant/après en JSONB.
 *
 * @property int $id
 * @property string $table_name 'tax_slabs' | 'social_contributions'
 * @property int $record_id
 * @property string $action created|submitted|approved|rejected|superseded
 * @property int $actor_id
 * @property string $actor_role
 * @property array<string, mixed>|null $previous_value
 * @property array<string, mixed> $new_value
 * @property string|null $reason
 * @property Carbon $created_at
 *
 * @mixin Builder<static>
 */
class TaxRateChangeLog extends Model
{
    public const TABLE_TAX_SLABS = 'tax_slabs';

    public const TABLE_SOCIAL_CONTRIBUTIONS = 'social_contributions';

    public const ACTION_CREATED = 'created';

    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_SUPERSEDED = 'superseded';

    protected $table = 'tax_rate_change_log';

    // Append-only : pas de gestion des timestamps Eloquent (created_at = NOW()).
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'record_id' => 'integer',
        'actor_id' => 'integer',
        'previous_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Verrou append-only : toute tentative d'UPDATE est bloquée.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('tax_rate_change_log est une table append-only : aucun UPDATE autorisé.');
    }

    /**
     * Verrou append-only : toute tentative de DELETE est bloquée.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('tax_rate_change_log est une table append-only : aucun DELETE autorisé.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Model $model): array
    {
        return $model->getAttributes();
    }
}
