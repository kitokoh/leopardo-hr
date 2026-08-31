<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read model de ventes journalières (TRAVEL-506, issue #6076).
 *
 * Agrégat recalculable par job idempotent : l'upsert par clé naturelle
 * `(company_id, sale_date, source, status, currency)` garantit qu'une
 * reprise du job donne un état identique (jamais d'accumulation).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property Carbon $sale_date
 * @property string $source
 * @property string $status
 * @property int $booking_count
 * @property int $passenger_count
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelDailySale extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'sale_date',
        'source',
        'status',
        'booking_count',
        'passenger_count',
        'amount_minor',
        'currency',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'booking_count' => 'integer',
        'passenger_count' => 'integer',
        'amount_minor' => 'integer',
    ];
}
