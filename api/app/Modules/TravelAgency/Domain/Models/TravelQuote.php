<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Devis corporate (TRAVEL-803, issue #6094).
 *
 * Prix TOUJOURS calculé serveur depuis les tarifs du trajet ; cycle
 * draft → accepted → cancelled|expired. Le devis accepté « fige » le
 * montant de la réservation de groupe.
use App\Modules\TravelAgency\Domain\Enums\QuoteStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Devis de groupe / corporate (TRAVEL-803, issue #6094).
 *
 * Total figé côté serveur (tarifs du trajet en unités mineures) ; la
 * réservation groupée ne peut pas dépasser ce plafond. Facturation différée
 * par événement outbox (contrat Accounting, spec D7).
 */
class TravelQuote extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'corporate_account_id',
        'trip_id',
        'class_id',
        'passengers_count',
        'total_amount_minor',
        'currency',
        'status',
        'expires_at',
    /** @use HasFactory<TravelQuoteFactory> */
    use HasFactory;

    /** Taille minimale d'un groupe (spec §12, TRAVEL-803). */
    public const MIN_GROUP_SIZE = 5;

    protected $fillable = [
        'reference',
        'trip_id',
        'status',
        'customer_contact_id',
        'passenger_count',
        'passengers_json',
        'total_amount_minor',
        'currency',
        'expires_at',
        'booking_id',
        'idempotency_key',
        'created_by_user_id',
    ];

    protected $casts = [
        'passengers_count' => 'integer',
        'total_amount_minor' => 'integer',
        'expires_at' => 'datetime',
    ];
        'passenger_count' => 'integer',
        'passengers_json' => 'array',
        'total_amount_minor' => 'integer',
        'status' => QuoteStatus::class,
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            if (empty($quote->reference)) {
                $quote->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'QT-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class, 'trip_id');
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_id');
    }
}
