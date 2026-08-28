<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cuve de stockage — Issue #5797 (FUEL-003).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $site_id
 * @property string $code
 * @property string $name
 * @property int|null $product_id
 * @property string $capacity
 * @property string $unit
 * @property string $min_level
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelTank extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_tanks';

    protected $fillable = ['company_id', 'site_id', 'code', 'name', 'product_id', 'capacity', 'unit', 'min_level', 'status', 'metadata'];

    protected $casts = [
        'site_id' => 'integer',
        'product_id' => 'integer',
        'capacity' => 'decimal:2',
        'min_level' => 'decimal:2',
        'metadata' => 'encrypted:array',
    ];
}
