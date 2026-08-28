<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelProduct;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_id
 * @property string $site_id
 * @property string $code
 * @property string $product
 * @property float $capacity
 * @property string $unit
 * @property float|null $current_level
 * @property string $status
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelTank extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_tanks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capacity' => 'float',
            'current_level' => 'float',
            'archived_at' => 'datetime',
        ];
    }

    public static function isValidProduct(string $product): bool
    {
        return FuelProduct::isValid($product);
    }
}
