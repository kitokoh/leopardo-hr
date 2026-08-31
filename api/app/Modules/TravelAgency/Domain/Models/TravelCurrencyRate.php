<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Taux de conversion multi-devise (TRAVEL-805, issue #6096).
 *
 * Validé par période (valid_from/valid_until) — un taux n'est utilisable
 * que si la date cible tombe dans sa fenêtre. Les montants canoniques
 * restent en minor units de la devise de référence du tenant.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $base_currency
 * @property string $quote_currency
 * @property float $rate
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelCurrencyRate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'base_currency',
        'quote_currency',
        'rate',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'rate' => 'float',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];
}
