<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelLoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compte de fidélité d'un voyageur (TRAVEL-811, issue #6101).
 *
 * L'opt-in RGPD est OBLIGATOIRE avant tout crédit de points ; le solde est
 * mis à jour transactionnellement avec les entrées de journal (jamais de
 * solde dérivé).
 * Compte fidélité voyageur (TRAVEL-811, issue #6101).
 *
 * Opt-in RGPD explicite : aucun point n'est crédité sans opt_in_at ;
 * l'opt-out gèle les crédits (le solde reste consultable).
 */
class TravelLoyaltyAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'contact_identifier',
        'points_balance',
        'opt_in',
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
        'opt_in' => 'boolean',
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
    ];

    /**
     * @return HasMany<TravelLoyaltyEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TravelLoyaltyEntry::class, 'account_id');
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
