<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelLoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compte fidélité voyageur (TRAVEL-811, issue #6101 — arbitrage #7445).
 *
 * Opt-in RGPD explicite : aucun point n'est crédité sans opt-in ; l'opt-out
 * gèle les crédits (le solde reste consultable).
 *
 * #7445 — **clé de contact = `contact_identifier`** (string : email ou
 * téléphone normalisé), et non `contact_id`. Deux migrations créaient la même
 * table avec des colonnes divergentes ; la plus ancienne gagne
 * (`2026_08_30_000012_6101`) et c'est elle qui porte `contact_identifier`,
 * `opt_in`, `opt_in_at`, `opt_out_at`. `contact_id` n'a jamais existé en base :
 * tout le code qui s'y référait écrivait une colonne absente et violait le
 * NOT NULL de `contact_identifier` (« /loyalty/opt-in » renvoyait 500). L'autre
 * implémentation (`LoyaltyPointsService`, `travel_loyalty_transactions`) a été
 * supprimée : il ne doit rester qu'UNE fidélité, adossée à
 * `travel_loyalty_accounts` + `travel_loyalty_entries`.
 */
class TravelLoyaltyAccount extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelLoyaltyAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_identifier',
        'points_balance',
        'opt_in',
        'opt_in_at',
        'opt_out_at',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'opt_in' => 'boolean',
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
    ];

    public function isOptedIn(): bool
    {
        return $this->opt_in === true && $this->opt_out_at === null;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TravelLoyaltyEntry::class, 'account_id');
    }
}
