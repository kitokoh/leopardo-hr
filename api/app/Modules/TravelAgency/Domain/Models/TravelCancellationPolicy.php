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
 * Appliquée côté serveur lors d'une annulation/remboursement : la pénalité
 * est toujours calculée par le serveur, jamais acceptée du client.
 */
class TravelCancellationPolicy extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCancellationPolicyFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'trip_id',
        'class_id',
        'cancel_before_hours',
        'penalty_percent',
        'refundable',
        'is_active',
        'description',
    ];

    protected $casts = [
        'trip_id' => 'integer',
        'class_id' => 'integer',
        'cancel_before_hours' => 'integer',
        'penalty_percent' => 'integer',
        'refundable' => 'boolean',
        'is_active' => 'boolean',
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
    public function travelClass(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }
}
