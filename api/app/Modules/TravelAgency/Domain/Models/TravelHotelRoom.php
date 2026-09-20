<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelHotelRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chambre d'un hôtel du catalogue TravelAgency (TRAVEL-214, issue #6027).
 *
 * `room_number` unique par hôtel (contrainte DB
 * `travel_hotel_rooms_company_hotel_room_unique`).
 *
 * @property int $capacity
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string $currency
 * @property int $hotel_id
 * @property int $id
 * @property int $price_per_night_minor
 * @property string $room_number
 * @property \App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus $status
 * @property string $type_code
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TravelHotelRoom extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelHotelRoomFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'type_code',
        'room_number',
        'capacity',
        'price_per_night_minor',
        'currency',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price_per_night_minor' => 'integer',
        'status' => TravelRecordStatus::class,
    ];

    /**
     * @return BelongsTo<TravelHotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(TravelHotel::class, 'hotel_id');
    }
}
