<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * #8750 [BOS-CHAT] — blocage unilatéral entre deux employés.
 *
 * Le blocage ne supprime AUCUNE donnée : il ferme deux portes, appliquées
 * côté serveur par {@see \App\Modules\Notification\Infrastructure\Services\ConversationModerationService} :
 *
 *  1. impossible de créer un nouveau fil `direct` avec la personne bloquée ;
 *  2. impossible de l'ajouter à un groupe dont on est `creator`/`admin`.
 *
 * L'historique existant reste lisible par les deux parties — bloquer n'est pas
 * effacer, et l'autre personne n'est pas notifiée du blocage (sinon un blocage
 * deviendrait un signal social explicite).
 *
 * @property int $id
 * @property string $company_id
 * @property int $blocker_id
 * @property int $blocked_id
 * @property string|null $reason
 * @property Carbon $created_at
 * @property-read Employee|null $blocker
 * @property-read Employee|null $blocked
 *
 * @mixin Builder<static>
 */
class ConversationBlock extends Model
{
    use BelongsToCompany;

    /** Le blocage n'a pas de cycle de vie : on le crée et on le supprime. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'blocker_id',
        'blocked_id',
        'reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'blocker_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'blocked_id');
    }
}
