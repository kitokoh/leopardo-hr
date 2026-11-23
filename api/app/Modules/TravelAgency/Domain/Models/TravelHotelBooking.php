<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Réservation hôtelière par date (M4).
 *
 * `idempotency_key` est unique par tenant : un rejeu réseau renvoie la MÊME
 * réservation au lieu d'en créer une seconde (et donc de consommer deux fois
 * l'inventaire).
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int $room_id
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property int $rooms
 * @property int $nights
 * @property int $total_amount_minor
 * @property string $currency
 * @property string $status
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelHotelBooking extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_hotel_bookings';

    protected $fillable = [
        'reference',
        'room_id',
        'check_in',
        'check_out',
        'rooms',
        'nights',
        'total_amount_minor',
        'currency',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'rooms' => 'integer',
        'nights' => 'integer',
        'total_amount_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            if (empty($booking->reference)) {
                $booking->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'HB-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<TravelHotelRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(TravelHotelRoom::class, 'room_id');
    }
}
