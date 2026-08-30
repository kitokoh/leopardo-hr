<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelLoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compte fidélité voyageur (TRAVEL-811, issue #6101).
 *
 * Opt-in RGPD explicite : aucun point n'est crédité sans opt_in_at ;
 * l'opt-out gèle les crédits (le solde reste consultable).
 */
class TravelLoyaltyAccount extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelLoyaltyAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'points_balance',
        'opt_in_at',
        'opt_out_at',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
    ];

    public function isOptedIn(): bool
    {
        return $this->opt_in_at !== null && $this->opt_out_at === null;
    }

    /**
     * @return HasMany<TravelLoyaltyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TravelLoyaltyTransaction::class, 'account_id');
    }
}
