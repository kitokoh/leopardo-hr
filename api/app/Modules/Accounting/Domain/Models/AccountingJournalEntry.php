<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Écriture du journal comptable salarial — flux Paie → Comptabilité (issue #5239).
 *
 * Persistance exacte de `PayrollAccountingExportService::journalLines()`
 * (lecture seule du run de paie validé, équilibre débit = crédit garanti par
 * construction). L'idempotence est portée par la contrainte UNIQUE
 * (company_id, payroll_run_id, pay_slip_id, account_code, debit, credit).
 *
 * @property int $id
 * @property string|null $company_id
 * @property Carbon $entry_date
 * @property int $payroll_run_id
 * @property int|null $pay_slip_id
 * @property int|null $employee_id
 * @property string $account_code
 * @property string $account_label
 * @property float $debit
 * @property float $credit
 * @property string $reference
 * @property string $source
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingJournalEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'company_id',
        'entry_date',
        'payroll_run_id',
        'pay_slip_id',
        'employee_id',
        'account_code',
        'account_label',
        'debit',
        'credit',
        'reference',
        'source',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function isDebit(): bool
    {
        return (float) $this->debit > 0.0;
    }
}
