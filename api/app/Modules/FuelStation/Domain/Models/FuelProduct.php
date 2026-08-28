<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Produit vendu (carburant, GPL, …) — Issue #5797 (FUEL-003).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelProduct extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_products';

    protected $fillable = ['company_id', 'code', 'name', 'unit', 'is_active', 'metadata'];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'encrypted:array',
    ];
}
