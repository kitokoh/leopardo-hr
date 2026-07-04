<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property int|null $company_id
 * @property int    $employee_id
 * @property string $title
 * @property float  $total_amount
 * @property string $status  (draft|submitted|approved|rejected|paid)
 * @property string|null $submitted_at
 * @property string|null $approved_at
 * @property string|null $paid_at
 * @property string|null $payment_reference
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExpenseClaim extends Model
{
    use BelongsToCompany;

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
        'approved_at'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Core\Auth\Domain\Models\Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }
}
