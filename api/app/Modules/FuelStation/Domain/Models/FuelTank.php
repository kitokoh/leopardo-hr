<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cuve de station — FUEL-003. FK composite anti cross-tenant.
 * Capacités en unités mineures entières (jamais de flottants métier).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $code
 * @property string $product_type
 * @property int $capacity_minor
 * @property int $current_level_minor
 * @property string $status  active|inactive|retired
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelTank extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_tanks';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'company_id',
        'station_id',
        'code',
        'product_type',
        'capacity_minor',
        'current_level_minor',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity_minor' => 'integer',
            'current_level_minor' => 'integer',
        ];
    }
}
