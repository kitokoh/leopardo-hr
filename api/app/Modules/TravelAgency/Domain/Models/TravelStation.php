<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gare / terminal de la verticale TravelAgency (TRAVEL-203, issue #6016).
 *
 * Point de départ et d'arrivée physique des trajets (ancien concept de
 * « ville » de gv-back enrichi) : code unique par tenant, ville de référence,
 * fuseau horaire local (affichage), indicateur terminal principal.
 */
class TravelStation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelStationFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city_id',
        'address',
        'contact_phone',
        'timezone',
        'is_terminal',
        'status',
    ];

    protected $casts = [
        'is_terminal' => 'boolean',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class);
    }
}
