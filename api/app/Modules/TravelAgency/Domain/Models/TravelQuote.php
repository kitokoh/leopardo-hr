<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

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
 *
 * @property int|null $booking_id
 * @property int|null $class_id
 * @property string $company_id
 * @property int|null $corporate_account_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string $currency
 * @property int|null $customer_contact_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int $id
 * @property string|null $idempotency_key
 * @property int|null $passenger_count
 * @property int|null $passengers_count
 * @property string|null $reference
 * @property \App\Modules\TravelAgency\Domain\Enums\QuoteStatus $status
 * @property int $total_amount_minor
 * @property int $trip_id
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TravelQuote extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelQuoteFactory> */
    use HasFactory;

    /** Taille minimale d'un groupe (spec §12, TRAVEL-803). */
    public const MIN_GROUP_SIZE = 5;

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_EXPIRED = 'expired';

    /**
     * #9098 — les colonnes qui font l'IDENTITE du devis doivent etre
     * assignables : `CorporateBookingService::createQuote()` ecrit
     * `corporate_account_id`, `class_id` et `passengers_count`, et
     * `Model::create()` FILTRE silencieusement tout attribut absent d'ici.
     * Le devis etait donc cree sans compte ni effectif, puis tout
     * `createGroupBooking()` adosse a ce devis repondait 422
     * (« devis incoherent avec le compte corporate ») : l'invariant
     * « devis respecte » ne pouvait structurellement jamais passer.
     *
     * `passenger_count` (singulier) est la colonne historique nullable ;
     * `passengers_count` porte l'effectif du devis corporate.
     */
    protected $fillable = [
        'reference',
        'trip_id',
        'corporate_account_id',
        'class_id',
        'passengers_count',
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
        'corporate_account_id' => 'integer',
        'class_id' => 'integer',
        'passenger_count' => 'integer',
        'passengers_count' => 'integer',
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
