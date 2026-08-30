<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Passe de rapprochement stock d'une station pour une date (FUEL-009, #5803).
 *
 * Un seul run par (company_id, station_id, run_date) — la contrainte unique
 * rend le job de rapprochement rejouable. `summary` jsonb porte le détail
 * par cuve : théorique attendu vs mesuré et écart. Aucun ajustement
 * silencieux : l'écart est rapporté, jamais corrigé en base.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property Carbon $run_date
 * @property string $status pending|running|completed|failed
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $last_error
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelReconciliationRun extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_reconciliation_runs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'run_date',
        'status',
        'summary',
        'started_at',
        'finished_at',
        'last_error',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'run_date' => 'date:Y-m-d',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
