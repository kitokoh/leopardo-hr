<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Billet nominatif (TRAVEL-210, issue #6023).
 *
 * `validation_code` stocke un hash SHA-256 — jamais le code en clair. Le
 * code en clair (destiné au QR) n'est retourné qu'une fois, à l'émission,
 * par `issue()` ; il n'est jamais persisté.
 */
class TravelTicket extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTicketFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'booking_id',
        'passenger_id',
        'validation_code',
        'pdf_asset_id',
        'issued_at',
        'valid_from',
        'valid_until',
        'status',
        'checked_in_at',
        'checked_in_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'status' => TicketStatus::class,
        'checked_in_at' => 'datetime',
    ];

    protected $hidden = [
        'validation_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        return '#GV-'.strtoupper(Str::random(10));
    }

    /**
     * Génère un code de validation en clair (destiné au QR, non persisté)
     * et enregistre son hash sur le billet.
     */
    public function issueValidationCode(): string
    {
        $plainCode = strtoupper(Str::random(16));
        $this->validation_code = hash('sha256', $plainCode);

        return $plainCode;
    }

    public function validationCodeMatches(string $plainCode): bool
    {
        return hash_equals($this->validation_code, hash('sha256', $plainCode));
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<TravelPassenger, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(TravelPassenger::class, 'passenger_id');
    }
}
