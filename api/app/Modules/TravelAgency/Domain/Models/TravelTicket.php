<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Billet nominatif (TRAVEL-210, issue #6023).
 *
 * `validation_code` stocke un hash SHA-256 — jamais le code en clair. C'est
 * LUI, et lui seul, qui sert à la vérification (`validationCodeMatches()`,
 * comparaison à temps constant).
 *
 * #7394 — le code en clair était généré puis **perdu** : absent de la réponse
 * d'émission, absent du PDF, jamais envoyé. Le portail passager (« Espace
 * voyageur ») exige pourtant ce code pour suivre une réservation : le parcours
 * ne pouvait pas aboutir. Une copie **chiffrée** (`validation_code_ciphertext`,
 * AES-256-GCM via APP_KEY) est donc conservée pour permettre la réimpression de
 * l'e-billet. Elle n'est jamais sérialisée (voir `$hidden`) et n'intervient pas
 * dans la vérification.
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
        'validation_code_ciphertext',
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
        'validation_code_ciphertext',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateTicketNumber();
            }

            // La colonne est NOT NULL en base : on enregistre le hash d'un
            // code aléatoire dès la création. `issueValidationCode()` reste
            // le seul chemin qui retourne le code EN CLAIR (QR), à l'émission.
            if (empty($ticket->validation_code)) {
                $ticket->validation_code = hash('sha256', strtoupper(Str::random(16)));
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        return '#GV-'.strtoupper(Str::random(10));
    }

    /**
     * Émet un code de validation en clair (destiné à l'e-billet).
     *
     * #7394 : le hash reste le support de vérification, mais le code est
     * désormais aussi conservé chiffré — sinon il était définitivement perdu
     * dès la fin de la requête et le passager ne pouvait jamais le recevoir.
     */
    public function issueValidationCode(): string
    {
        $plainCode = strtoupper(Str::random(16));
        $this->validation_code = hash('sha256', $plainCode);
        $this->validation_code_ciphertext = Crypt::encryptString($plainCode);

        return $plainCode;
    }

    /**
     * Code de validation en clair, si une émission a eu lieu après #7394.
     *
     * Retourne `null` pour les billets émis avant le correctif (le clair est
     * perdu, aucune copie chiffrée n'existe) ou si la clé d'application a été
     * changée entre-temps — auquel cas l'e-billet affiche une mention explicite
     * plutôt qu'un faux code.
     */
    public function plainValidationCode(): ?string
    {
        if (! is_string($this->validation_code_ciphertext) || $this->validation_code_ciphertext === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->validation_code_ciphertext);
        } catch (Throwable) {
            report(new RuntimeException(
                "travel.ticket.validation_code_undecryptable ticket={$this->id} — clé d'application changée ?"
            ));

            return null;
        }
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
