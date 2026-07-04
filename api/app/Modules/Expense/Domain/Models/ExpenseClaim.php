<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property int    $employee_id
 * @property string $title
 * @property float  $total_amount
 * @property string $status  (draft|submitted|approved|rejected|reimbursed)
 * @property string|null $submitted_at
 * @property string|null $approved_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExpenseClaim extends Model
{
    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'total_amount',
        'currency',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
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
