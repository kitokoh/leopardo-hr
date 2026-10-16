<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Prix de vente daté d'un produit FuelStation — P0-1 (audit 2026-10-16).
 *
 * Le prix courant d'un produit à un instant donné est la ligne `active` dont
 * `effective_from` est le plus récent antérieur ou égal à cet instant. Ce
 * modèle porte donc à la fois le prix courant et l'historique des changements
 * de prix (exigence du domaine « Produits et prix »).
 *
 * `unit_price_minor` est un entier (unités mineures de la devise) : aucune
 * arithmétique flottante sur des montants monétaires.
 *
 * @property int $id
 * @property string $company_id
 * @property string $product_code
 * @property string $unit_code
 * @property int $unit_price_minor
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status active|inactive
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelProductPrice extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_product_prices';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'product_code',
        'unit_code',
        'unit_price_minor',
        'effective_from',
        'effective_to',
        'status',
        'created_by',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
