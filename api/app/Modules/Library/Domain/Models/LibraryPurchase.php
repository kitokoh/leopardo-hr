<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Models;

use App\Modules\Library\Domain\Enums\LibraryPurchaseStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vente d'une ressource de la bibliothèque (BC-40 / BC-38, #9238).
 *
 * La vente est la SEULE preuve d'achat : `LibraryAccessPolicy` ne décide rien
 * par elle-même, elle reçoit le drapeau `purchased` calculé ici par
 * `LibraryPurchaseService::hasPaidAccess()`. Une ressource `purchase` sans
 * ligne `paid` reste donc illisible — fail-closed par construction.
 *
 * Les montants sont FIGÉS à la vente (le prix du catalogue peut changer, la
 * facture non) et `net_minor + vat_minor === total_minor` par construction
 * (`LibraryPricing::breakdown()`).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property int $resource_id
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $total_minor
 * @property int $net_minor
 * @property int $vat_minor
 * @property string $vat_rate
 * @property string $currency
 * @property LibraryPurchaseStatus $status
 * @property string $idempotency_key
 * @property int|null $payment_transaction_id
 * @property string|null $payment_reference
 * @property string|null $invoice_number
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class LibraryPurchase extends Model
{
    use BelongsToCompany;

    protected $table = 'library_purchases';

    protected $fillable = [
        'company_id',
        'employee_id',
        'resource_id',
        'quantity',
        'unit_price_minor',
        'total_minor',
        'net_minor',
        'vat_minor',
        'vat_rate',
        'currency',
        'status',
        'idempotency_key',
        'payment_transaction_id',
        'payment_reference',
        'invoice_number',
        'paid_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LibraryPurchaseStatus::class,
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'total_minor' => 'integer',
            'net_minor' => 'integer',
            'vat_minor' => 'integer',
            'vat_rate' => 'float',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LibraryResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(LibraryResource::class, 'resource_id');
    }

    /**
     * Cette vente ouvre-t-elle l'accès au contenu ?
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
