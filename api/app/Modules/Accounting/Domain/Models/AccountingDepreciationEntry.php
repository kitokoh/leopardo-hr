<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle Entrée d'amortissement (#9377).
 *
 * @property int $id
 * @property string $company_id
 * @property int $fixed_asset_id
 * @property string $period
 * @property float $amount
 * @property float $cumulative_amount
 * @property string|null $journal_entry_group
 * @property string $status
 */
class AccountingDepreciationEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'fixed_asset_id',
        'period',
        'amount',
        'cumulative_amount',
        'journal_entry_group',
        'status',
    ];

    protected $casts = [
        'fixed_asset_id' => 'integer',
        'amount' => 'decimal:2',
        'cumulative_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<AccountingFixedAsset, $this>
     */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(AccountingFixedAsset::class, 'fixed_asset_id');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }
}
