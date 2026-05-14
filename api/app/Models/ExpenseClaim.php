<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property string $title
 * @property string $description
 * @property float $total_amount
 * @property string $currency
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $approved_by
 * @property string|null $payment_reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /** @return HasMany<ExpenseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class, 'expense_claim_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function recalculateTotal(): void
    {
        $this->update(['total_amount' => $this->items()->sum('amount')]);
    }
}
