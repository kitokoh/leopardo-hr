<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horaire d'ouverture d'une branche par jour de la semaine (RESTO-204, issue #6169).
 *
 * `day_of_week` suit la convention ISO (0 = lundi ... 6 = dimanche) ;
 * `is_closed` indique une fermeture hebdomadaire (heures ignorées).
 */
class RestaurantHour extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantHourFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
