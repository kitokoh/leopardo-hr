<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $expense_claim_id
 * @property string|null $category
 * @property string $description
 * @property float $amount
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $receipt_path
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ExpenseItem extends Model
{
    public $timestamps = false;

    protected $table = 'expense_items';

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
        'date' => 'date',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ExpenseClaim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
