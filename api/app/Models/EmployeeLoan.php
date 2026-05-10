<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLoan extends Model
{
    use BelongsToCompany;

    protected $table = 'employee_loans';

    protected $fillable = [
        'company_id',
        'employee_id',
        'loan_type',
        'amount',
        'currency',
        'interest_rate',
        'installments',
        'installment_amount',
        'start_date',
        'status',
        'approved_by',
        'disbursed_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'interest_rate' => 'float',
        'installment_amount' => 'float',
        'start_date' => 'date',
        'disbursed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class, 'employee_loan_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['disbursed', 'repaying']);
    }

    public function remainingAmount(): float
    {
        $paid = $this->repayments()->where('status', 'paid')->sum('amount');

        return (float) $this->amount - (float) $paid;
    }
}
