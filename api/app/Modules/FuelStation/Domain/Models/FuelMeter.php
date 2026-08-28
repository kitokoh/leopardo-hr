<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compteur d'une pompe — Issue #5797 (FUEL-003).
 *
 * Invariant : un seul compteur actif par pompe (index partiel UNIQUE
 * `fuel_meters_active_per_pump_unique`).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $pump_id
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property bool $is_active
 * @property string $last_reading
 * @property Carbon|null $installed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeter extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_meters';

    protected $fillable = ['company_id', 'pump_id', 'code', 'name', 'unit', 'is_active', 'last_reading', 'installed_at', 'metadata'];

    protected $casts = [
        'pump_id' => 'integer',
        'is_active' => 'boolean',
        'last_reading' => 'decimal:3',
        'installed_at' => 'datetime',
        'metadata' => 'encrypted:array',
    ];
}
