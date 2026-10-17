<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\LedgerDirection;
use App\Modules\Paiements\Domain\Exceptions\ImmutableLedgerEntryException;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8681) — ecriture de ledger, en partie double.
 *
 * Chaque mouvement produit **exactement deux** ecritures (un debit + un credit)
 * partageant le meme `transfer_group_id`. Invariant verifiable a tout moment :
 * pour un groupe donne, `SUM(debit) == SUM(credit)` et le nombre d'ecritures est
 * pair.
 *
 * **Immuabilite** : les gardes `saving`/`deleting` ci-dessous font echouer toute
 * tentative de modification ou de suppression, y compris depuis un code qui
 * contournerait la couche service. Une correction passe par une ecriture de
 * contre-passation (nouveau groupe inverse).
 *
 * @property int $id
 * @property string $company_id
 * @property int $wallet_id
 * @property LedgerDirection $direction
 * @property int $amount
 * @property string $currency
 * @property int|null $transaction_id
 * @property string $transfer_group_id
 * @property string|null $reference
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class PaymentLedgerEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'payment_ledger_entries';

    /** Journal : `created_at` seulement, jamais d'`updated_at`. */
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'wallet_id',
        'direction',
        'amount',
        'currency',
        'transaction_id',
        'transfer_group_id',
        'reference',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'direction' => LedgerDirection::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            // `saving` se declenche aussi a la creation : on ne bloque que les
            // mises a jour d'une ligne deja persistee.
            if ($entry->exists) {
                throw ImmutableLedgerEntryException::onUpdate((int) $entry->getKey());
            }
        });

        static::deleting(function (self $entry): void {
            throw ImmutableLedgerEntryException::onDelete((int) $entry->getKey());
        });
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    /** @return BelongsTo<PaymentTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForGroup(Builder $query, string $transferGroupId): Builder
    {
        return $query->where('transfer_group_id', $transferGroupId);
    }
}
