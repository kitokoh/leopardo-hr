<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelOfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bureau de vente de l'agence (TRAVEL-203, issue #6016).
 *
 * Point de vente physique (guichet) de la verticale — ancien concept
 * « agences » de gv-back. Les ventes `booking_source=office` y sont
 * rattachées (épic 3xx).
 */
class TravelOffice extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelOfficeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'city_id',
        'address',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(TravelCity::class, 'city_id');
    }
}
