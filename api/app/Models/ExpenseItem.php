<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
