<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ligne d'écriture comptable publiée par FuelStation — FUEL-015 (#5809).
 *
 * Produite par `FuelAccountingContractService` à partir des agrégats validés
 * (ventes du jour, clôtures de caisse, écarts de stock rapprochés), consommée
 * par le module Accounting. Référence traçable `FUEL-*` — UNIQUE
 * (company_id, reference) → régénération idempotente.
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property Carbon $period
 * @property string $entry_type sales|cash_session|stock_variance
 * @property string $account_code
 * @property string $account_label
 * @property float $debit
 * @property float $credit
 * @property string $reference
 * @property int|null $created_by
 *
 * @mixin Builder<static>
 */
class FuelAccountingEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_accounting_entries';

    public const TYPE_SALES = 'sales';

    public const TYPE_CASH_SESSION = 'cash_session';

    public const TYPE_STOCK_VARIANCE = 'stock_variance';

    protected $fillable = [
        'company_id',
        'station_id',
        'period',
        'entry_type',
        'account_code',
        'account_label',
        'debit',
        'credit',
        'reference',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'debit' => 'float',
            'credit' => 'float',
        ];
    }
}
