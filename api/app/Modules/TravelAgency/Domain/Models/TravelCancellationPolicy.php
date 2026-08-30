<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCancellationPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Politique d'annulation configurable (TRAVEL-813, issue #6103).
 *
 * Spécificité décroissante : (trajet, classe) > (classe) > (trajet) >
 * défaut tenant. Consommée par TravelRefundPolicyResolver (TRAVEL-808).
 */
class TravelCancellationPolicy extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCancellationPolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'class_id',
        'hours_before_departure',
        'penalty_percent',
        'refundable',
        'created_by_user_id',
    ];

    protected $casts = [
        'hours_before_departure' => 'integer',
        'penalty_percent' => 'integer',
        'refundable' => 'boolean',
    ];

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class, 'trip_id');
    }

    /**
     * @return BelongsTo<TravelClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }
}
