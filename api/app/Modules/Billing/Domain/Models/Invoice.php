<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

// Note: App\Modules\Payroll\Domain\Models\Payment is intentionally NOT imported here.
// Invoice (Billing) must not depend on Payroll's Domain layer — that would create a
// circular Domain<->Domain dependency (Invoice -> Payment -> Invoice).
// The `payments()` relation passes the CLASS NAME AS A STRING (non-FQCN identifier)
// so that Eloquent resolves the model at runtime without any compile-time reference
// to Payroll — l'ADR-0011 impose le FQCN, et le fixer Pint `fully_qualified_strict_types`
// (preset laravel) réécrit les identifiants `\Foo\Bar::class` ; une chaîne n'est
// touchée ni par Pint ni par PHPStan.
// See: docs/architecture/adr/0011-billing-payroll-domain-boundary.md  — Issue #1395.
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

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
 *
 * @mixin Builder<static>
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

    // ADR-0011 : FQCN dans le PHPDoc (PHPStan/IDE) mais JAMAIS importé — la
    // relation passe une chaîne littérale (résolution runtime par Eloquent,
    // zéro dépendance compile-time Billing → Payroll). Le fixer Pint
    // fully_qualified_strict_types est désactivé dans api/pint.json (aligné
    // sur l'ADR — voir le commentaire du fichier).
    /**
     * @return HasMany<\App\Modules\Payroll\Domain\Models\Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany('App\Modules\Payroll\Domain\Models\Payment');
    }

    /**
     * Transition d'état gardée (DEP-BC21 #6248).
     *
     * La machine à états est définie par {@see InvoiceStatus} : toute
     * transition invalide lève InvalidArgumentException. Une transition vers
     * le statut COURANT est idempotente (sans exception) : elle ne fait que
     * synchroniser les attributs additionnels (paid_at, payment_method…) —
     * contrat utilisé par les webhooks providers rejoués.
     *
     * @param  array<string, mixed>  $extra  attributs additionnels (paid_at, payment_method…)
     */
    public function transitionTo(InvoiceStatus $status, array $extra = []): self
    {
        $current = InvoiceStatus::tryFrom((string) $this->status) ?? InvoiceStatus::Draft;

        if ($current !== $status && ! $current->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Transition de facture invalide : {$current->value} → {$status->value}"
            );
        }

        $this->forceFill([
            'status' => $status->value,
            ...$extra,
        ])->save();

        return $this;
    }
}
