<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Modules\Vtc\Domain\Enums\VtcRideEventType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement du journal append-only d'une course (BC-34 VTC, VTC-02/#8358).
 *
 * IMMUABLE : `created_at` seul, aucune mise à jour — chaque transition de la
 * state machine et chaque étape du dispatch y écrit une ligne (VTC-04).
 * Isolation tenant : trait BelongsToCompany (fail-closed).
 *
 * @property int $id
 * @property string $company_id
 * @property int $ride_id
 * @property VtcRideEventType $type
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class VtcRideEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'vtc_ride_events';

    /** Append-only : pas de updated_at (colonne absente du schéma). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'ride_id',
        'type',
        'payload',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ride_id' => 'integer',
            'type' => VtcRideEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VtcRide, $this> */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(VtcRide::class, 'ride_id');
    }
}
