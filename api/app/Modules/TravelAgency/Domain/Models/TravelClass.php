<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Classe de service de la verticale TravelAgency (TRAVEL-204, issue #6017).
 *
 * Référentiel tenant-scoped (ex. Économique/Business) — code unique par
 * tenant, priorité d'affichage. Consommée par `travel_trip_prices`
 * (tarif par trajet/classe).
 */
class TravelClass extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Database\Factories\TravelClassFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'color',
        'priority',
        'status',
    ];

    protected $casts = [
        'priority' => 'integer',
        'status' => TravelRecordStatus::class,
    ];
}
