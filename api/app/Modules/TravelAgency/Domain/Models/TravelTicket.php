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
 * Billet nominatif (TRAVEL-210, issue #6023 — corrigé #7394).
 *
 * `validation_code` stocke un hash SHA-256 — jamais le code en clair. Le code
 * de contrôle est DÉRIVÉ du numéro de billet (HMAC-SHA256 + `APP_KEY`) : il
 * n'est donc jamais persisté en clair, reste imprimable sur l'e-billet à tout
 * moment (PDF/QR), et n'est renvoyé par l'API qu'UNE SEULE FOIS, à l'émission
 * (`issuedValidationCode`) — jamais par une route de lecture.
 */
class TravelTicket extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelTicketFactory> */
    use HasFactory;

    /**
     * #7394 — Contexte du HMAC : évite toute collision avec un autre usage de
     * `APP_KEY` (le code ne doit être reproductible QUE par ce chemin).
     */
    private const VALIDATION_CODE_CONTEXT = 'travel-ticket-validation-code:v1:';

    /**
     * Alphabet sans caractères ambigus (ni 0/O, ni 1/I/L) : le code est
     * recopié à la main par un passager. 32 symboles — 256 est un multiple de
     * 32, donc aucun biais modulo.
     */
    private const VALIDATION_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** Longueur utile du code (avant présentation en 3 groupes de 4). */
    private const VALIDATION_CODE_LENGTH = 12;

    /**
     * Code de contrôle EN CLAIR, renseigné UNIQUEMENT au moment de l'émission
     * (#7394). Simple propriété PHP — jamais un attribut Eloquent : elle n'est
     * ni persistée, ni sérialisée, et disparaît avec l'instance. Seul
     * `TravelTicketResource` la lit, pour délivrer le code une seule fois.
     */
    public ?string $issuedValidationCode = null;

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

            // La colonne est NOT NULL en base : on enregistre dès la création
            // le hash du code canonique (#7394), pour que le code imprimé sur
            // l'e-billet soit toujours celui qu'accepte l'API.
            if (empty($ticket->validation_code)) {
                $ticket->validation_code = self::hashValidationCode(
                    self::canonicalValidationCode((string) $ticket->ticket_number)
                );
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        return '#GV-'.strtoupper(Str::random(10));
    }

    /**
     * Code de contrôle canonique d'un billet — XXXX-XXXX-XXXX (#7394).
     *
     * Dérivé du numéro de billet via HMAC-SHA256 + `APP_KEY` : reproductible
     * côté serveur (PDF, portail) sans jamais être persisté en clair, et
     * impossible à recalculer sans le secret applicatif.
     */
    public static function canonicalValidationCode(string $ticketNumber): string
    {
        $digest = hash_hmac(
            'sha256',
            self::VALIDATION_CODE_CONTEXT.$ticketNumber,
            (string) config('app.key'),
            true,
        );

        $code = '';

        for ($i = 0; $i < self::VALIDATION_CODE_LENGTH; $i++) {
            $code .= self::VALIDATION_CODE_ALPHABET[ord($digest[$i]) % 32];
        }

        return implode('-', str_split($code, 4));
    }

    /**
     * Normalise une saisie utilisateur (casse, espaces, tirets) vers la forme
     * canonique — le passager recopie le code à la main.
     */
    public static function normalizeValidationCode(string $plainCode): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plainCode));
    }

    public static function hashValidationCode(string $plainCode): string
    {
        return hash('sha256', self::normalizeValidationCode($plainCode));
    }

    /**
     * Code de contrôle EN CLAIR du billet (#7394) — destiné à l'impression
     * (PDF/QR) et à la délivrance unique à l'émission. Jamais un attribut
     * persisté : c'est une dérivation du numéro de billet et d'`APP_KEY`.
     */
    public function plainValidationCode(): string
    {
        return self::canonicalValidationCode((string) $this->ticket_number);
    }

    /**
     * Émission : scelle le hash du code canonique et retourne le code EN
     * CLAIR (délivré une seule fois, jamais relu depuis la base).
     */
    public function issueValidationCode(): string
    {
        $plainCode = $this->plainValidationCode();
        $this->validation_code = self::hashValidationCode($plainCode);

        return $plainCode;
    }

    /**
     * Vérifie un code fourni (hash stocké en priorité) — comparaison à temps
     * constant. Le code canonique reste accepté pour les billets dont le code
     * en clair avait été perdu avant #7394 (leur hash est alors orphelin).
     */
    public function validationCodeMatches(string $plainCode): bool
    {
        $normalized = self::normalizeValidationCode($plainCode);

        if ($normalized === '') {
            return false;
        }

        if (hash_equals((string) $this->validation_code, self::hashValidationCode($normalized))) {
            return true;
        }

        return hash_equals(self::normalizeValidationCode($this->plainValidationCode()), $normalized);
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
