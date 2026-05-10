<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseClaim extends Model
{
    use BelongsToCompany;

    protected $table = 'expense_claims';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'description',
        'total_amount',
        'currency',
        'status',
        'submitted_at',
        'approved_at',
        'paid_at',
        'approved_by',
        'payment_reference',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class, 'expense_claim_id');
    }

    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function recalculateTotal(): void
    {
        $this->update(['total_amount' => $this->items()->sum('amount')]);
    }
}
