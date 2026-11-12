<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Immobilisation amortissable (issue #9378).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property Carbon $in_service_date
 * @property float $acquisition_cost
 * @property float $residual_value
 * @property int $useful_life_months
 * @property string $method
 * @property string|null $currency
 * @property string $status
 * @property Carbon|null $disposed_on
 * @property string|null $notes
 */
class AccountingFixedAsset extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_fixed_assets';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISPOSED = 'disposed';

    public const METHOD_LINEAR = 'linear';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'code',
        'label',
        'in_service_date',
        'acquisition_cost',
        'residual_value',
        'useful_life_months',
        'method',
        'currency',
        'status',
        'disposed_on',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'in_service_date' => 'date',
            'acquisition_cost' => 'float',
            'residual_value' => 'float',
            'useful_life_months' => 'integer',
            'disposed_on' => 'date',
        ];
    }

    /** @return HasMany<AccountingDepreciation, $this> */
    public function depreciations(): HasMany
    {
        return $this->hasMany(AccountingDepreciation::class, 'fixed_asset_id');
    }

    /**
     * Base amortissable : coût d'acquisition moins valeur résiduelle.
     */
    public function depreciableBase(): float
    {
        return round(max(0.0, (float) $this->acquisition_cost - (float) $this->residual_value), 2);
    }

    /**
     * Dotation linéaire d'une période pleine.
     *
     * Calcul : base amortissable / durée en mois, arrondi au centime. La dernière
     * période est plafonnée par le service pour ne jamais dépasser la base (une
     * dotation arrondie sur 36 mois ne tombe pas juste).
     */
    public function monthlyDepreciation(): float
    {
        if ((int) $this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableBase() / (int) $this->useful_life_months, 2);
    }
}
