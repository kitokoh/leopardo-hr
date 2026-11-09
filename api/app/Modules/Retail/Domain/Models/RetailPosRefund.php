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
 * Remboursement d'une commande POS Retail (BC-17 RETAIL, #9208).
 *
 * Un remboursement est TOTAL ou PARTIEL : le cumul des remboursements d'une
 * commande ne peut jamais depasser son total encaisse (garde applicative dans
 * `RetailPosService::refundOrder`). `reason` est obligatoire — un
 * remboursement sans motif est un trou d'audit. `idempotency_key` unique par
 * tenant absorbe les doubles soumissions (un rejeu hors-ligne ne rembourse
 * pas deux fois).
 *
 * Montants en minor units. Cree UNIQUEMENT via RetailPosService (transaction).
 *
 * @property int $id
 * @property string $company_id
 * @property int $order_id
 * @property int|null $pos_session_id
 * @property RetailPaymentMethod $method
 * @property int $amount_minor
 * @property string $currency
 * @property string $reason
 * @property int|null $user_id
 * @property Carbon|null $refunded_at
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailPosRefund extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_pos_refunds';

    protected $fillable = [
        'company_id',
        'order_id',
        'pos_session_id',
        'method',
        'amount_minor',
        'currency',
        'reason',
        'user_id',
        'refunded_at',
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
            'refunded_at' => 'datetime',
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
