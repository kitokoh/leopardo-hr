<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Modules\Vtc\Domain\Enums\VtcVehicleCategory;
use App\Modules\Vtc\Domain\Enums\VtcVehicleStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\VtcVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Véhicule VTC/taxi (BC-34 VTC, VTC-02/#8358).
 *
 * Isolation tenant : trait BelongsToCompany (scope global + auto-remplissage
 * de company_id, fail-closed). Plaque unique par tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property string $plate
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $color
 * @property int $seats
 * @property VtcVehicleCategory $category
 * @property VtcVehicleStatus $status
 */
final class VtcVehicle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<VtcVehicleFactory> */
    use HasFactory;

    protected $table = 'vtc_vehicles';

    protected $fillable = [
        'plate',
        'brand',
        'model',
        'color',
        'seats',
        'category',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'category' => VtcVehicleCategory::class,
            'status' => VtcVehicleStatus::class,
        ];
    }

    /** @return HasMany<VtcDriver> */
    public function drivers(): HasMany
    {
        return $this->hasMany(VtcDriver::class, 'vehicle_id');
    }
}
