<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Models;

use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'écriture comptable générée automatiquement à l'approbation d'une
 * {@see ExpenseClaim} (issue #5235 — notes de frais → écritures comptables,
 * Phase C).
 *
 * Produite par `ExpenseAccountingEntryService::generateForClaim()` (partie
 * double : débit charge / crédit personnel), consommée par le module
 * Accounting (référence traçable `EXPENSE-{id}`). Le module Expense reste
 * maître du workflow des notes de frais.
 *
 * @property int $id
 * @property string $company_id
 * @property int $expense_claim_id
 * @property int|null $employee_id
 * @property Carbon $date
 * @property string $account_code
 * @property string $account_label
 * @property float $debit
 * @property float $credit
 * @property string $reference
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ExpenseAccountingEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'expense_accounting_entries';

    protected $fillable = [
        'company_id', 'expense_claim_id', 'employee_id', 'date',
        'account_code', 'account_label', 'debit', 'credit', 'reference',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
    ];

    /** @return BelongsTo<ExpenseClaim, $this> */
    public function expenseClaim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
