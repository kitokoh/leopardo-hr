<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

// Note: App\Modules\Payroll\Domain\Models\Payment is intentionally NOT imported here.
// Invoice (Billing) must not depend on Payroll's Domain layer — that would create a
// circular Domain<->Domain dependency (Invoice -> Payment -> Invoice).
// The `payments()` relation uses the FQCN string so that Eloquent can resolve the
// model at runtime without introducing a compile-time cross-module dependency.
// See: docs/architecture/adr/0005-billing-payroll-domain-boundary.md  — Issue #1395.
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property string|null $payment_method
 * @property int|null $stripe_invoice_id
 * @property string|null $pdf_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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

    /** @return HasMany<\App\Modules\Payroll\Domain\Models\Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(\App\Modules\Payroll\Domain\Models\Payment::class);
    }
}
