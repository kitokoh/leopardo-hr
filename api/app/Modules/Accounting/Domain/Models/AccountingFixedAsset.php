<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle Immobilisation (#9377).
 *
 * @property int $id
 * @property string $company_id
 * @property string $label
 * @property string $asset_account_code
 * @property string $depreciation_account_code
 * @property string $expense_account_code
 * @property string $acquisition_date
 * @property float $acquisition_cost
 * @property float $residual_value
 * @property int $useful_life_months
 * @property string $method
 * @property string $status
 * @property string|null $disposal_date
 * @property float|null $disposal_proceeds
 * @property string|null $notes
 */
class AccountingFixedAsset extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    /** Statut d'une immobilisation en service, amortie normalement. */
    public const STATUS_ACTIVE = 'active';

    /** Statut d'une immobilisation cédée : plus aucun amortissement n'est comptabilisé. */
    public const STATUS_DISPOSED = 'disposed';

    /** Statut d'une immobilisation intégralement amortie : la dotation est plafonnée. */
    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    /** Amortissement linéaire. */
    public const METHOD_LINEAR = 'linear';

    /** Amortissement dégressif. */
    public const METHOD_DECLINING = 'declining';

    protected $fillable = [
        'label',
        'asset_account_code',
        'depreciation_account_code',
        'expense_account_code',
        'acquisition_date',
        'acquisition_cost',
        'residual_value',
        'useful_life_months',
        'method',
        'status',
        'disposal_date',
        'disposal_proceeds',
        'notes',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    /**
     * @return HasMany<AccountingDepreciationEntry, $this>
     */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AccountingDepreciationEntry::class, 'fixed_asset_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDisposed(): bool
    {
        return $this->status === 'disposed';
    }

    public function isFullyDepreciated(): bool
    {
        return $this->status === 'fully_depreciated';
    }

    public function getDepreciableAmount(): float
    {
        return $this->acquisition_cost - $this->residual_value;
    }
}
