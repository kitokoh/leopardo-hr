<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'écriture comptable générée automatiquement à la validation d'un
 * {@see PayrollRun} (issue #5239 — flux Paie → Comptabilité, Phase C).
 *
 * Produite par `PayrollAccountingExportService::journalLines()` (socle
 * #5256) puis persistée par `PayrollAccountingEntryService::generateForRun()`.
 * Le module Payroll reste maître du calcul ; ces lignes sont consommées par
 * le module Accounting.
 *
 * @property int $id
 * @property string $company_id
 * @property int $payroll_run_id
 * @property int|null $pay_slip_id
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
class PayrollAccountingEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'pay_slip_id', 'employee_id', 'date',
        'account_code', 'account_label', 'debit', 'credit', 'reference',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
    ];

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /** @return BelongsTo<PaySlip, $this> */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class);
    }
}
