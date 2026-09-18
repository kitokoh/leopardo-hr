<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Issue #7598 (R1 de l'épique #7597) — une ressource d'un tenant, donnée à UN
 * collaborateur, à UN niveau d'accès.
 *
 * C'est le seul endroit du modèle RBAC où une autorisation porte sur une
 * **ressource nommée** (« Moussa → restaurant Almadies ») et non sur un rôle
 * global. Les niveaux sont ordonnés (`view` < `operate` < `manage`) : une
 * policy qui exige `operate` est satisfaite par `manage`, jamais l'inverse.
 *
 * `Auditable` : toute pose / modification / révocation écrit une ligne dans
 * `audit_logs` (exigence de l'issue : « chaque changement → AuditLog »), sans
 * code dédié dans les contrôleurs.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $employee_id
 * @property string $resource_type
 * @property int $resource_id
 * @property string $access_level
 * @property int|null $created_by
 */
class EmployeeResourceAssignment extends Model
{
    use Auditable;
    use BelongsToCompany;

    public const LEVEL_VIEW = 'view';

    public const LEVEL_OPERATE = 'operate';

    public const LEVEL_MANAGE = 'manage';

    /**
     * Niveaux du plus faible au plus fort. L'ordre EST le contrat : une
     * exigence `view` est satisfaite par `view`, `operate` et `manage` ;
     * `operate` par `operate` et `manage` ; `manage` par `manage` seul.
     *
     * @var list<string>
     */
    public const ACCESS_LEVELS = [self::LEVEL_VIEW, self::LEVEL_OPERATE, self::LEVEL_MANAGE];

    /**
     * `company_id` est délibérément ABSENT : il est posé explicitement par
     * l'appelant depuis le contexte tenant (même garde qu'`Employee` et
     * `Department`, #3597 — un `company_id` mass-assignable laisserait un
     * appelant choisir le tenant d'écriture).
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'resource_type',
        'resource_id',
        'access_level',
    ];

    /**
     * Rang d'un niveau, ou -1 si la valeur est inconnue. Fail-closed : une
     * valeur inattendue en base ne satisfait aucune exigence plutôt que de
     * passer pour `view` par accident.
     */
    public static function levelRank(string $level): int
    {
        $rank = array_search($level, self::ACCESS_LEVELS, true);

        return $rank === false ? -1 : $rank;
    }

    /** Un niveau `actual` satisfait-il l'exigence `min` ? */
    public static function satisfies(string $actual, string $min): bool
    {
        $actualRank = self::levelRank($actual);
        $minRank = self::levelRank($min);

        return $actualRank >= 0 && $minRank >= 0 && $actualRank >= $minRank;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
