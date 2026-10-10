<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Geo\GeoPoint;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\VtcDriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chauffeur VTC/taxi (BC-34 VTC, VTC-02/#8358).
 *
 * Isolation tenant : trait BelongsToCompany (fail-closed). Implémente
 * GeoLocatable : la dernière position connue (colonnes décimales WGS 84) est
 * requêtable par le core géospatial BC-33 — type `vtc_driver` de la registry
 * `geo.searchables`, enregistré dans VtcServiceProvider (dispatch VTC-04 :
 * chauffeur disponible le plus proche, jamais de calcul local).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property VtcDriverStatus $status
 * @property int|null $vehicle_id
 * @property float|null $current_latitude
 * @property float|null $current_longitude
 * @property \Illuminate\Support\Carbon|null $location_updated_at
 */
final class VtcDriver extends Model implements GeoLocatable
{
    use BelongsToCompany;

    /** @use HasFactory<VtcDriverFactory> */
    use HasFactory;

    protected $table = 'vtc_drivers';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'status',
        'vehicle_id',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'vehicle_id' => 'integer',
            'status' => VtcDriverStatus::class,
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VtcVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(VtcVehicle::class, 'vehicle_id');
    }

    /** @return HasMany<VtcRide, $this> */
    public function rides(): HasMany
    {
        return $this->hasMany(VtcRide::class, 'driver_id');
    }

    /** @return HasMany<VtcDriverPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(VtcDriverPosition::class, 'driver_id');
    }

    public function geoPoint(): ?GeoPoint
    {
        return GeoPoint::fromNullable($this->current_latitude, $this->current_longitude);
    }

    public function geoLabel(): string
    {
        return $this->name;
    }

    public static function geoLatitudeColumn(): string
    {
        return 'current_latitude';
    }

    public static function geoLongitudeColumn(): string
    {
        return 'current_longitude';
    }
}
