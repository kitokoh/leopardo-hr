<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rapport de rapprochement stock/cuves — FUEL-009, issue #5803.
 *
 * Un rapport PAR STATION ET PAR JOUR (UNIQUE (company_id, station_id,
 * report_date)) : le job de rapprochement est REJOUABLE — relancer
 * recalcule et remplace le rapport sans doublon. `variance_minor` =
 * closing − expected ; tout écart reste `pending_review` jusqu'à la revue
 * manager qui saisit une explication (aucun ajustement silencieux).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property Carbon $report_date format Y-m-d
 * @property int $opening_stock_minor
 * @property int $deliveries_minor
 * @property int $sales_minor
 * @property int $expected_stock_minor
 * @property int|null $closing_stock_minor
 * @property int $variance_minor
 * @property string $status pending_review|reviewed|approved
 * @property string|null $explanation
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelReconciliationReport extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_reconciliation_reports';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUSES = [self::STATUS_PENDING_REVIEW, self::STATUS_REVIEWED, self::STATUS_APPROVED];

    protected $fillable = [
        'company_id',
        'station_id',
        'report_date',
        'opening_stock_minor',
        'deliveries_minor',
        'sales_minor',
        'expected_stock_minor',
        'closing_stock_minor',
        'variance_minor',
        'status',
        'explanation',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'report_date' => 'date',
            'opening_stock_minor' => 'integer',
            'deliveries_minor' => 'integer',
            'sales_minor' => 'integer',
            'expected_stock_minor' => 'integer',
            'closing_stock_minor' => 'integer',
            'variance_minor' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
