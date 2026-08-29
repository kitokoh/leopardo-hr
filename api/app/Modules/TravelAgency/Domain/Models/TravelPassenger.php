<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor;
use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passager d'une réservation (TRAVEL-209, issue #6022).
 *
 * Le n° de pièce d'identité n'est **jamais** stocké ni exposé en clair
 * (Constitution §V — RGPD) : `document_number_encrypted` (chiffré via
 * `SensitiveDataEncryptor`) et `document_number_hash` (recherche exacte
 * sans déchiffrer) sont les seules colonnes persistées. Utiliser
 * `setDocumentNumber()`/`getDocumentNumber()` plutôt que d'assigner les
 * colonnes brutes directement ; les API Resources ne doivent jamais
 * exposer `document_number_encrypted` ni le retour de `getDocumentNumber()`.
 */
class TravelPassenger extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Database\Factories\TravelPassengerFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'full_name',
        'birth_date',
        'document_type',
        'document_number_encrypted',
        'document_number_hash',
        'age_category',
        'class_id',
        'seat_number',
        'unit_price_minor',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'document_type' => DocumentType::class,
        'age_category' => AgeCategory::class,
        'seat_number' => 'integer',
        'unit_price_minor' => 'integer',
    ];

    protected $hidden = [
        'document_number_encrypted',
        'document_number_hash',
    ];

    public function setDocumentNumber(string $plainDocumentNumber): void
    {
        $encryptor = app(SensitiveDataEncryptor::class);

        $this->document_number_encrypted = $encryptor->encrypt($plainDocumentNumber);
        $this->document_number_hash = hash('sha256', $plainDocumentNumber);
    }

    public function getDocumentNumber(): ?string
    {
        if (empty($this->document_number_encrypted)) {
            return null;
        }

        return app(SensitiveDataEncryptor::class)->decrypt($this->document_number_encrypted);
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class);
    }

    /**
     * @return BelongsTo<TravelClass, $this>
     */
    public function travelClass(): BelongsTo
    {
        return $this->belongsTo(TravelClass::class, 'class_id');
    }
}
