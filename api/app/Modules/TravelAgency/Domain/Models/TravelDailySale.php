<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-506 (#6076) — Read model : ventes journalières par trajet.
 * Recalculé par job idempotent (upsert par clé unique tenant+date+trajet).
 *
 * @mixin Builder<static>
use Illuminate\Database\Eloquent\Model;

/**
 * Read model de ventes journalières (TRAVEL-506, issue #6076).
 *
 * Agrégat recalculable par job idempotent : l'upsert par clé naturelle
 * `(company_id, sale_date, source, status, currency)` garantit qu'une
 * reprise du job donne un état identique (jamais d'accumulation).
 */
class TravelDailySale extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_daily_sales';

    protected $fillable = [
        'company_id',
        'sale_date',
        'trip_id',
        'bookings_count',
        'passengers_count',
        'revenue_minor',
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
        'bookings_count' => 'integer',
        'passengers_count' => 'integer',
        'revenue_minor' => 'integer',
        'booking_count' => 'integer',
        'passenger_count' => 'integer',
        'amount_minor' => 'integer',
    ];
}
