<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    protected $table = 'loan_repayments';

    protected $fillable = [
        'employee_loan_id',
        'company_id',
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

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }
}
