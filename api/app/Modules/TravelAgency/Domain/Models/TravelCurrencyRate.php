<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCurrencyRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Taux de conversion par tenant (TRAVEL-805, issue #6096).
 *
 * `rate_minor` = taux × 10000 (entier) : la conversion reste en math entière
 * (unités mineures × rate_minor / 10000) — aucune perte d'arrondi.
 * Valide sur [valid_from, valid_to] (valid_to NULL = période ouverte).
 */
class TravelCurrencyRate extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCurrencyRateFactory> */
    use HasFactory;

    /** Facteur de normalisation du taux (4 décimales). */
    public const RATE_SCALE = 10000;

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate_minor',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'rate_minor' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
