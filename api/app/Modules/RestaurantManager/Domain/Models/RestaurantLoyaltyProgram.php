<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantLoyaltyProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Programme de fidélité du tenant (RESTO-212, issue #6177).
 *
 * `points_per_amount_minor` : points gagnés par tranche dépensée ;
 * `redeem_rate_minor` : valeur (minor units) d'un point à l'échange.
 */
class RestaurantLoyaltyProgram extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantLoyaltyProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'points_per_amount_minor',
        'redeem_rate_minor',
        'is_active',
    ];

    protected $casts = [
        'points_per_amount_minor' => 'integer',
        'redeem_rate_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * #9077 — `is_active` vaut `true` par defaut en base, mais Eloquent ne
     * recharge pas les valeurs par defaut apres un INSERT : un programme cree
     * sans `is_active` explicite portait `null` en memoire alors qu'il etait
     * actif en base. Toute logique qui lit l'attribut sur l'instance qui vient
     * d'etre creee (dont l'invariant « un seul programme actif ») voyait donc
     * `null` et se comportait comme si le programme etait inactif.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];
}
