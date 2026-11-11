<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dotation aux amortissements d'une période (issue #9378).
 *
 * Une ligne par (immobilisation, période) : c'est la clé d'idempotence du
 * calcul — relancer la dotation d'un mois déjà traité ne double jamais la
 * charge.
 *
 * @property int $id
 * @property string $company_id
 * @property int $fixed_asset_id
 * @property string $period
 * @property Carbon $entry_date
 * @property float $amount
 * @property float $accumulated_after
 * @property float $net_book_value_after
 * @property string|null $currency
 * @property int|null $journal_entry_source_id
 */
class AccountingDepreciation extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_depreciations';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'fixed_asset_id',
        'period',
        'entry_date',
        'amount',
        'accumulated_after',
        'net_book_value_after',
        'currency',
        'journal_entry_source_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'float',
            'accumulated_after' => 'float',
            'net_book_value_after' => 'float',
        ];
    }

    /** @return BelongsTo<AccountingFixedAsset, $this> */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(AccountingFixedAsset::class, 'fixed_asset_id');
    }
}
