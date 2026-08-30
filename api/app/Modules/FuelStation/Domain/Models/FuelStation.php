<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Station-service (site opérationnel) — FUEL-002.
 *
 * Toute donnée FuelStation est tenant-scoped ; le code est unique par
 * tenant (jamais global). Une station possède un ou plusieurs sites
 * opérationnels (`fuel_sites`) — cf. `FuelSite`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string $timezone
 * @property string|null $currency
 * @property array<string, mixed>|null $metadata
 * @property string $status active|inactive|archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStation extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_stations';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'phone',
        'timezone',
        'currency',
        'metadata',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'timezone' => 'string',
            'metadata' => 'encrypted:array',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<FuelSite, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(FuelSite::class, 'station_id');
    }
}
