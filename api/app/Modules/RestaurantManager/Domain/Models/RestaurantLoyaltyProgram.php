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
}
