<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Visite/activité client d'une station — FUEL-016 (#5810).
 *
 * `idempotency_key` UNIQUE (company_id, idempotency_key) → un rejeu ne
 * crédite jamais deux fois la fidélité. Tenant-scoped via FK composites.
 *
 * @property int $id
 * @property string $company_id
 * @property int $customer_id
 * @property int $station_id
 * @property Carbon $visited_at
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property int|null $created_by
 *
 * @mixin Builder<static>
 */
class FuelCustomerVisit extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_customer_visits';

    protected $fillable = [
        'company_id',
        'customer_id',
        'station_id',
        'visited_at',
        'notes',
        'idempotency_key',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
