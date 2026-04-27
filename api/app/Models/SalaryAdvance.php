<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvance extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'salary_advances';

    protected $fillable = [
        'company_id', 'employee_id', 'amount', 'reason', 'status',
        'approved_by', 'decision_comment', 'repayment_months',
        'monthly_deduction', 'amount_remaining', 'repayment_plan',
    ];

    protected $casts = [
        'amount' => 'float', 'monthly_deduction' => 'float',
        'amount_remaining' => 'float', 'repayment_plan' => 'array',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class, 'employee_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(Employee::class, 'approved_by'); }

    public function scopePending(Builder $q): Builder { return $q->where('status', 'pending'); }
    public function scopeActive(Builder $q): Builder { return $q->where('status', 'active'); }
    public function scopeForEmployee(Builder $q, int $id): Builder { return $q->where('employee_id', $id); }
}
