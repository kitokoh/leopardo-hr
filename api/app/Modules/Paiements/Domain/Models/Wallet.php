<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\LedgerDirection;
use App\Modules\Paiements\Domain\Enums\WalletOwnerType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8680) — wallet d'un proprietaire (plateforme, tenant, client).
 *
 * **Le solde n'est jamais stocke.** `balance()` agrege le ledger a la demande :
 * `SUM(credits) - SUM(debits)`. Un cache serait une seconde source de verite ;
 * s'il est un jour ajoute, il devra etre invalide dans la meme transaction que
 * l'ecriture, et une commande d'artisan devra pouvoir detecter l'ecart.
 *
 * @property int $id
 * @property string $company_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $currency
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Wallet extends Model
{
    use BelongsToCompany;

    protected $table = 'payment_wallets';

    protected $fillable = [
        'company_id',
        'owner_type',
        'owner_id',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'owner_type' => WalletOwnerType::class,
        'metadata' => 'array',
    ];

    /** @return HasMany<PaymentLedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(PaymentLedgerEntry::class, 'wallet_id');
    }

    /**
     * Solde derive du ledger, en plus petite unite de la devise.
     *
     * Une seule requete agregee : jamais de replay en PHP, jamais de colonne
     * de solde a maintenir.
     */
    public function balance(): int
    {
        // Agregation via le modele du Domain (pas de facade Laravel ici : le
        // Domain ne depend que de lui-meme, regle de purete des couches #6568).
        /** @var object{credits: int|string, debits: int|string}|null $row */
        $row = PaymentLedgerEntry::query()
            ->where('wallet_id', $this->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS credits,'
                .' COALESCE(SUM(CASE WHEN direction = ? THEN amount ELSE 0 END), 0) AS debits',
                [LedgerDirection::Credit->value, LedgerDirection::Debit->value],
            )
            ->first();

        if ($row === null) {
            return 0;
        }

        return (int) $row->credits - (int) $row->debits;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, WalletOwnerType $ownerType, string $ownerId): Builder
    {
        return $query->where('owner_type', $ownerType->value)->where('owner_id', $ownerId);
    }
}
