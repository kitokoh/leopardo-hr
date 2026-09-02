<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPublicShopTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jeton de la boutique publique RestaurantManager (RESTO-805, issue #6226).
 *
 * Un par tenant ; seul le hash SHA-256 est persiste. La rotation
 * (regeneration) invalide l'ancien jeton. Consomme par le middleware
 * `restaurant.public.shop` pour resoudre le tenant sans authentification
 * utilisateur (pattern TravelPublicShopToken, TRAVEL-1001/#6114).
 */
class RestaurantPublicShopToken extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPublicShopTokenFactory> */
    use HasFactory;

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
