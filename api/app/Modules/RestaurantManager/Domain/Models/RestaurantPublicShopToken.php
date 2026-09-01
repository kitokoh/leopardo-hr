<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * RESTO-805 (#6226) — Jeton de la boutique publique RestaurantManager.
 *
 * Un par tenant ; seul le hash SHA-256 est persisté. La rotation
 * (régénération) invalide l'ancien jeton. Les endpoints publics
 * `/public/restaurant/*` résolvent le tenant via ce jeton (middleware
 * `restaurant.public.shop`), le scope BelongsToCompany s'applique ensuite —
 * aucune fuite cross-tenant (critère d'acceptation).
 */
class RestaurantPublicShopToken extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'token_hash',
        'name',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
