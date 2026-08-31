<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rapport de rapprochement compteurs ↔ ventes ↔ stock — FUEL-009 (#5803).
 *
 * Rejouable : un même couple (station, produit, période, clé) retourne le
 * rapport existant. Chaque composante du calcul est conservée dans
 * `explanation` (jsonb) pour qu'un écart soit TOUJOURS explicable.
 *
 * Statuts :
 * - `pending_measurement` : pas de jauge de clôture renseignée (écart non
 *   calculable) ;
 * - `completed` : variance ≤ tolérance ;
 * - `exception` : variance > tolérance — aucun ajustement silencieux, une
 *   décision manager explicite est requise (mouvement `adjustment`).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $product_type
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $status pending_measurement|completed|exception
 * @property int $opening_minor
 * @property int $delivered_minor
 * @property int $sold_minor
 * @property int $metered_delta_minor
 * @property int|null $measured_close_minor
 * @property int $theoretical_close_minor
 * @property int $variance_minor
 * @property int $variance_tolerance_minor
 * @property array<string, mixed>|null $explanation
 * @property string $idempotency_key
 * @property int|null $started_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStockReconciliation extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stock_reconciliations';

    public const STATUS_PENDING_MEASUREMENT = 'pending_measurement';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXCEPTION = 'exception';

    public const STATUSES = [
        self::STATUS_PENDING_MEASUREMENT,
        self::STATUS_COMPLETED,
        self::STATUS_EXCEPTION,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'product_type',
        'period_start',
        'period_end',
        'status',
        'opening_minor',
        'delivered_minor',
        'sold_minor',
        'metered_delta_minor',
        'measured_close_minor',
        'theoretical_close_minor',
        'variance_minor',
        'variance_tolerance_minor',
        'explanation',
        'idempotency_key',
        'started_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_minor' => 'integer',
            'delivered_minor' => 'integer',
            'sold_minor' => 'integer',
            'metered_delta_minor' => 'integer',
            'measured_close_minor' => 'integer',
            'theoretical_close_minor' => 'integer',
            'variance_minor' => 'integer',
            'variance_tolerance_minor' => 'integer',
            'explanation' => 'array',
            'completed_at' => 'datetime',
            'started_by' => 'integer',
        ];
    }
}
