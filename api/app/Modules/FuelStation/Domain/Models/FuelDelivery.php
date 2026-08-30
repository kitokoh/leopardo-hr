<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Livraison de carburant FuelStation — FUEL-009 (issue #5803).
 *
 * Cycle draft → received → verified. À la réception, un mouvement de stock
 * `delivery` est enregistré (stock physiquement entré). `external_id` UNIQUE
 * (company_id, external_id) → rejeu d'import idempotent.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $tank_id
 * @property string $product_type
 * @property int $quantity_minor
 * @property Carbon $delivered_at
 * @property string $source manual|supplier|import
 * @property string $status draft|received|verified
 * @property string|null $external_id
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property string|null $notes
 *
 * @mixin Builder<static>
 */
class FuelDelivery extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_deliveries';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VERIFIED = 'verified';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECEIVED,
        self::STATUS_VERIFIED,
    ];

    public const SOURCES = ['manual', 'supplier', 'import'];

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'product_type',
        'quantity_minor',
        'delivered_at',
        'source',
        'status',
        'external_id',
        'received_by',
        'received_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_minor' => 'integer',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
