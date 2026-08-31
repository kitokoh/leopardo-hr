<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelSalesSettlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Synthèse périodique des ventes TravelAgency pour Accounting
 * (TRAVEL-417, issue #6069).
 *
 * Agrégat idempotent : la contrainte unique (company_id, période, devise)
 * garantit qu'un même période n'est jamais synthétisée deux fois avec des
 * montants différents. L'événement `travel.sales.settled.v1` est la source
 * de vérité pour les écritures côté Accounting.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $currency
 * @property int $confirmed_payments_count
 * @property int $confirmed_amount_minor
 * @property int $refunded_count
 * @property int $refunded_amount_minor
 * @property int $net_amount_minor
 * @property string $status
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelSalesSettlement extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelSalesSettlementFactory> */
    use HasFactory;

    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'company_id',
        'period_start',
        'period_end',
        'currency',
        'confirmed_payments_count',
        'confirmed_amount_minor',
        'refunded_count',
        'refunded_amount_minor',
        'net_amount_minor',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'confirmed_payments_count' => 'integer',
        'confirmed_amount_minor' => 'integer',
        'refunded_count' => 'integer',
        'refunded_amount_minor' => 'integer',
        'net_amount_minor' => 'integer',
        'settled_at' => 'datetime',
    ];
}
