<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rapprochement stock/compteurs/ventes par station et par jour
 * — FUEL-009 (issue #5803).
 *
 * UNIQUE (company_id, station_id, run_date) → le job de rapprochement est
 * rejouable sans doublon (l'état est mis à jour, jamais dupliqué). Le
 * `summary` (jsonb) porte les écarts par produit et leur explication :
 * aucun ajustement silencieux, chaque écart est explicable.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property Carbon $run_date
 * @property string $status pending|running|completed|failed
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $summary
 * @property string|null $last_error
 * @property int|null $created_by
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

    protected $fillable = [
        'company_id',
        'station_id',
        'run_date',
        'status',
        'started_at',
        'finished_at',
        'summary',
        'last_error',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'run_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
            'created_by' => 'integer',
        ];
    }
}
