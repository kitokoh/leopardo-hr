<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\VtcFareProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grille tarifaire VTC (BC-34 VTC, VTC-02/#8358).
 *
 * Montants en MINOR UNITS (base, par km, par minute, minimum) — le calcul de
 * prix (VTC-03) applique max(minimum, base + km×per_km + min×per_minute).
 * Isolation tenant : trait BelongsToCompany (fail-closed).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $currency
 * @property int $base_minor
 * @property int $per_km_minor
 * @property int $per_minute_minor
 * @property int $minimum_minor
 * @property bool $is_default
 */
final class VtcFareProfile extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<VtcFareProfileFactory> */
    use HasFactory;

    protected $table = 'vtc_fare_profiles';

    protected $fillable = [
        'name',
        'currency',
        'base_minor',
        'per_km_minor',
        'per_minute_minor',
        'minimum_minor',
        'is_default',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'base_minor' => 'integer',
            'per_km_minor' => 'integer',
            'per_minute_minor' => 'integer',
            'minimum_minor' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /** @return HasMany<VtcRide> */
    public function rides(): HasMany
    {
        return $this->hasMany(VtcRide::class, 'fare_profile_id');
    }
}
