<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paiement d'une commande de vente Retail (BC-17 RETAIL, #7674).
 *
 * Multi-moyens par commande (`cash|card|mobile|online` — `online` reserve a
 * la future boutique e-commerce, aucune passerelle en v1). `idempotency_key`
 * unique par tenant absorbe les doubles soumissions. Montants en minor
 * units. Cree UNIQUEMENT via RetailPosService (transaction).
 *
 * @property int $id
 * @property string $company_id
 * @property int $order_id
 * @property int|null $pos_session_id
 * @property RetailPaymentMethod $method
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailOrderPayment extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_order_payments';

    protected $fillable = [
        'company_id',
        'order_id',
        'pos_session_id',
        'method',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'reference',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => RetailPaymentMethod::class,
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RetailOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RetailOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<RetailPosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(RetailPosSession::class, 'pos_session_id');
    }
}
