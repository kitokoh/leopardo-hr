<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inventaire d'une chambre d'hôtel pour UNE date (M4).
 *
 * `rooms_total` = capacité vendable ce soir-là (0 = chambre fermée),
 * `rooms_booked` = déjà vendu. L'invariant `rooms_booked <= rooms_total` est
 * tenu par la contrainte DB `travel_hotel_inventory_booked_check` ET par
 * l'Action de réservation, qui verrouille les lignes (`FOR UPDATE`) avant de
 * décider — la contrainte seule ne suffit pas à produire un 409 lisible.
 *
 * @property int $id
 * @property string $company_id
 * @property int $room_id
 * @property Carbon $stay_date
 * @property int $rooms_total
 * @property int $rooms_booked
 * @property TravelRecordStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelHotelRoomInventory extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_hotel_room_inventories';

    protected $fillable = [
        'room_id',
        'stay_date',
        'rooms_total',
        'rooms_booked',
        'status',
    ];

    protected $casts = [
        'stay_date' => 'date',
        'rooms_total' => 'integer',
        'rooms_booked' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * Places encore vendables pour cette date.
     */
    public function remaining(): int
    {
        return max(0, $this->rooms_total - $this->rooms_booked);
    }

    /**
     * Lignes d'inventaire d'une chambre sur l'intervalle [from, to] inclus.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRoomRange(Builder $query, int $roomId, string $from, string $to): Builder
    {
        return $query->where('room_id', $roomId)
            ->whereBetween('stay_date', [$from, $to])
            ->orderBy('stay_date');
    }

    /**
     * @return BelongsTo<TravelHotelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(TravelHotelRoom::class, 'room_id');
    }
}
