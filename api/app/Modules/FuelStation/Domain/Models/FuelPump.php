<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pompe de station — FUEL-003. FK composite (station_id, company_id) :
 * impossible de rattacher une pompe à la station d'un autre tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property string $code
 * @property array<int, string>|null $product_types
 * @property string $status active|inactive|retired
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelPump extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_pumps';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'company_id',
        'station_id',
        'code',
        'product_types',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_types' => 'array',
        ];
    }
}
