<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $subscription_id
 * @property string|null $number
 * @property string $amount
 * @property string $currency
 * @property string $tax_amount
 * @property string $total
 * @property string $status
 * @property \Illuminate\Support\Carbon $due_date
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $payment_method
 * @property int|null $stripe_invoice_id
 * @property string|null $pdf_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Invoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'subscription_id',
        'number',
        'amount',
        'currency',
        'tax_amount',
        'total',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
        'stripe_invoice_id',
        'pdf_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
