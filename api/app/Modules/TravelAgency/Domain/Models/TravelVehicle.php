<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\ValueObjects\SeatLayout;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule de la flotte propre de l'agence (TRAVEL-205, issue #6018).
 *
 * `carrier_id` nullable : un véhicule propre à l'agence n'appartient à
 * aucune compagnie tierce (`travel_carriers`), il peut aussi être rattaché
 * à un transporteur si l'agence opère pour son compte.
 *
 * @property int|null $carrier_id
 * @property string $code
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int $id
 * @property string|null $notes
 * @property string|null $registration_number
 * @property int $seat_capacity
 * @property array<string, mixed>|null $seat_layout Disposition physique (#8644) — null = non configuré.
 * @property \App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus $status
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float|null $latitude
 * @property float|null $longitude
 * @property \App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource|null $position_source
 * @property \Illuminate\Support\Carbon|null $position_recorded_at
 */
class TravelVehicle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelVehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'registration_number',
        'seat_capacity',
        'seat_layout',
        'carrier_id',
        'status',
        'notes',
        'latitude',
        'longitude',
        'position_source',
        'position_recorded_at',
    ];

    protected $casts = [
        'seat_capacity' => 'integer',
        'seat_layout' => 'array',
        'status' => TravelRecordStatus::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'position_source' => TravelVehiclePositionSource::class,
        'position_recorded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class, 'carrier_id');
    }

    /**
     * #8644 — disposition PHYSIQUE configurée, ou `null` si le véhicule n'a
     * jamais été configuré. On ne devine rien ici : la valeur brute peut être
     * corrompue, `SeatLayout::tryFromArray()` renvoie alors `null`.
     */
    public function seatLayout(): ?SeatLayout
    {
        $raw = $this->seat_layout;

        // #8652 — la capacité est transmise pour que les sièges absents soient
        // bornés par le véhicule réel : un siège absent hors capacité rend la
        // disposition inexploitable (repli), il n'est jamais accepté tel quel.
        return SeatLayout::tryFromArray(is_array($raw) ? $raw : null, (int) $this->seat_capacity);
    }

    /**
     * #8644 — disposition EFFECTIVE utilisée par le plan de sièges.
     *
     * Configuration réelle si présente, sinon repli historique sur la
     * capacité. Le booléen `is_configured` permet aux surfaces d'annoncer
     * honnêtement qu'une disposition a été déduite et non lue.
     *
     * @return array{seats_per_row: int, aisle_after: int|null, absent_seats?: list<int>, is_configured: bool}
     */
    public function effectiveSeatLayout(?int $capacity = null): array
    {
        $configured = $this->seatLayout();

        if ($configured instanceof SeatLayout) {
            return [...$configured->toArray(), 'is_configured' => true];
        }

        $fallback = SeatLayout::fallbackForCapacity($capacity ?? (int) $this->seat_capacity);

        return [...$fallback->toArray(), 'is_configured' => false];
    }

    /**
     * #8644 — disposition effective d'un véhicule ABSENT.
     *
     * Un trajet sans véhicule affecté n'a pas de géométrie connue : on renvoie
     * le repli, mais `is_configured` reste `false` pour que l'appelant ne
     * prétende jamais lire une configuration inexistante.
     *
     * @return array{seats_per_row: int, aisle_after: int|null, absent_seats?: list<int>, is_configured: bool}
     */
    public static function fallbackSeatLayout(int $capacity): array
    {
        return [
            ...SeatLayout::fallbackForCapacity($capacity)->toArray(),
            'is_configured' => false,
        ];
    }
}
