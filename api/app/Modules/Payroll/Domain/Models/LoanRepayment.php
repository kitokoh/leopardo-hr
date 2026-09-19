<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $employee_loan_id
 * @property int|null $company_id
 * @property Carbon $due_date
 * @property float $amount
 * @property float $principal
 * @property float $interest
 * @property string $status
 * @property Carbon|null $paid_at
 * @property int|null $payroll_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class LoanRepayment extends Model
{
    // Issue #7711 (suite #7646) — table `loan_repayments` du schéma partagé
    // shared_tenants : company_id est l'unique frontière d'isolation.
    use BelongsToCompany;

    protected $table = 'loan_repayments';

    protected $fillable = [
        'employee_loan_id',
        'due_date',
        'amount',
        'principal',
        'interest',
        'status',
        'paid_at',
        'payroll_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'float',
        'principal' => 'float',
        'interest' => 'float',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<EmployeeLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }
}
