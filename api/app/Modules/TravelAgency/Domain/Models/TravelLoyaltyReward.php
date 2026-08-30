<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Récompense du programme de fidélité (TRAVEL-811, issue #6101).
 */
class TravelLoyaltyReward extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'points_cost',
        'active',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'active' => 'boolean',
    ];
}
