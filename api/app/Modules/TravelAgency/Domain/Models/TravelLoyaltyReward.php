<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Récompense du programme de fidélité (TRAVEL-811, issue #6101).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $description
 * @property int $points_cost
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
