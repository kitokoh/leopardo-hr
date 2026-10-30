<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantLoyaltyCustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solde de points d'un client fidélité (RESTO-212, issue #6177).
 *
 * Un seul compte par (tenant, contact client) ; `tier_code` identifie le
 * palier (bronze, silver, gold...) par valeur, sans table dédiée.
 */
class RestaurantLoyaltyCustomer extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantLoyaltyCustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_contact_id',
        'points',
        'tier_code',
        // #9077 — `opted_in_at` est ecrit par `storeCustomer()` (opt-in RGPD,
        // RESTO-606/#6211) et lu par la ressource API. Absent de `$fillable`,
        // l'attribut etait silencieusement JETE par l'affectation de masse :
        // la ligne etait creee avec `opted_in_at = NULL`, donc l'opt-in RGPD
        // n'etait jamais horodate alors que l'API repondait 201.
        'opted_in_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'opted_in_at' => 'datetime',
    ];

    /**
     * #9077 — la colonne `points` vaut `0` par defaut en base, mais Eloquent ne
     * recharge PAS les valeurs par defaut apres un INSERT : l'instance creee
     * portait `points = null` en memoire, et la ressource API renvoyait
     * `"points": null` pour un compte tout juste ouvert. Un client qui lit le
     * solde a l'opt-in recevait `null` au lieu de `0`. Declarer l'attribut ici
     * aligne l'etat en memoire sur le schema, sans requete supplementaire.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points' => 0,
    ];

    /**
     * @return HasMany<RestaurantLoyaltyPointsMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(RestaurantLoyaltyPointsMovement::class, 'customer_id');
    }
}
