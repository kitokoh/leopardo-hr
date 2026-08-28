<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vente de carburant / produit annexe d'une station-service
 * (FUEL-008, issue #5802).
 *
 * Le montant (amount = quantity × unit_price) est calculé SERVEUR — jamais
 * accepté du client. `external_id` (unicité par tenant) rend l'enregistrement
 * idempotent (rejeu → retour de la vente existante, zéro doublon).
 * `pump_id`/`station_id` (uuid) résolus par FUEL-003/FUEL-002.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $station_id
 * @property string|null $pump_id
 * @property string|null $cash_session_id
 * @property int $employee_id
 * @property string $product
 * @property float $quantity
 * @property float $unit_price
 * @property float $amount
 * @property Carbon $sale_time
 * @property string $source manual|kiosk|pos
 * @property string|null $external_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @mixin Builder<static>
 */
class FuelSale extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_sales';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_KIOSK = 'kiosk';

    public const SOURCE_POS = 'pos';

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_KIOSK, self::SOURCE_POS];

    protected $fillable = [
        'company_id',
        'station_id',
        'pump_id',
        'cash_session_id',
        'employee_id',
        'product',
        'quantity',
        'unit_price',
        'amount',
        'sale_time',
        'source',
        'external_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
        'sale_time' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
