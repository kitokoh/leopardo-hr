<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Livraison de carburant dans une cuve (FUEL-009, issue #5803).
 *
 * Append-only et idempotente : `external_id` unique par tenant — un rejeu
 * renvoie la livraison existante (zéro doublon). `quantity_minor` en unités
 * mineures entières (jamais de flottant métier). FK composite (tank_id,
 * company_id) → fuel_tanks : impossible de livrer la cuve d'un autre tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int $tank_id
 * @property int $quantity_minor
 * @property int|null $unit_price_minor
 * @property Carbon $delivered_at
 * @property string|null $external_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelTankDelivery extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_tank_deliveries';

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'tank_id',
        'quantity_minor',
        'unit_price_minor',
        'delivered_at',
        'external_id',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tank_id' => 'integer',
            'quantity_minor' => 'integer',
            'unit_price_minor' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FuelTank, $this> */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'tank_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
