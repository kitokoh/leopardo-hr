<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelPumpStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pompe d'une station — Issue #5797 (FUEL-003).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $site_id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property int|null $product_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelPump extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_pumps';

    protected $fillable = ['company_id', 'site_id', 'code', 'name', 'status', 'product_id', 'metadata'];

    protected $casts = [
        'site_id' => 'integer',
        'product_id' => 'integer',
        'metadata' => 'encrypted:array',
    ];

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FuelPumpStatus::Active->value);
    }
}
