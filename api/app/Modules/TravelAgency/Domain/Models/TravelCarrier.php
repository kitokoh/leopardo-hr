<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\CarrierType;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCarrierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Compagnie de transport de la verticale TravelAgency (TRAVEL-204, issue #6017).
 *
 * Référentiel tenant-scoped des transporteurs (bus/train/avion/bateau) —
 * code unique par tenant. Consommé par `travel_vehicles` (flotte propre,
 * carrier_id nullable) et `travel_trips`.
 *
 * @property string $code
 * @property string $company_id
 * @property string|null $contact_phone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int $id
 * @property int|null $logo_asset_id
 * @property string $name
 * @property \App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus $status
 * @property \App\Modules\TravelAgency\Domain\Enums\CarrierType $type
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TravelCarrier extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCarrierFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'contact_phone',
        'logo_asset_id',
        'status',
    ];

    protected $casts = [
        'type' => CarrierType::class,
        'status' => TravelRecordStatus::class,
    ];
}
