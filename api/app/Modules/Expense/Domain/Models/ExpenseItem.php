<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $expense_claim_id
 * @property string $category
 * @property string $description
 * @property float  $amount
 * @property string $expense_date
 * @property string|null $receipt_path
 */
class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'category',
        'description',
        'amount',
        'currency',
        'expense_date',
        'receipt_path',
    ];

    protected $casts = [
        'amount'       => 'float',
        'expense_date' => 'date',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
