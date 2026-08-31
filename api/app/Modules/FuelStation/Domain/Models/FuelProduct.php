<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Produit vendu par une station (carburant, GPL, …) — Issue #5797 (FUEL-003).
 *
 * Catalogue tenant-scoped : `code` unique par tenant ; les équipements
 * (pompes/cuves/compteurs) référencent ces codes au niveau application.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $unit_code
 * @property string $status active|inactive
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

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'unit_code',
        'status',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'encrypted:array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
