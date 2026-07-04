<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Colonnes reelles de la table `expense_items`
 * (migration tenant 2026_05_10_000007_create_loans_and_expenses_tables.php).
 *
 * @property int    $id
 * @property int    $expense_claim_id
 * @property string $category
 * @property string $description
 * @property float  $amount
 * @property string $date
 * @property string|null $receipt_path
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'category',
        'description',
        'amount',
        'date',
        'receipt_path',
    ];

    protected $casts = [
        'amount' => 'float',
        'date'   => 'date',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
