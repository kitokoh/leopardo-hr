<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Code de vérification SMS d'un dossier de Page (BC PAGES, #8725).
 *
 * ## Le code n'est jamais stocké en clair
 *
 * Un code à 6 chiffres dans une base est une base de codes vérifiables : qui
 * lit la table peut approuver n'importe quel dossier. On ne stocke donc que
 * l'empreinte HMAC-SHA256 (avec le `APP_KEY`, donc non pré-calculable hors du
 * serveur) — la vérification recalcule l'empreinte du code présenté et compare
 * en temps constant (`hash_equals`).
 *
 * ## Les trois garde-fous exigés par l'issue
 *
 * - **`expires_at` obligatoire** (TTL par défaut 10 min) : un code sans
 *   expiration est un mot de passe permanent, souvent jamais changé.
 * - **`attempts`/`max_attempts`** (par défaut 5) : sans plafond, 6 chiffres se
 *   bruteforcent par un script en quelques minutes. Le compteur est incrémenté
 *   **avant** la comparaison (`attempts` porte les tentatives consommées), pour
 *   qu'un crash entre les deux ne rende pas une tentative gratuite.
 * - **`cooldown_until`** : sans cooldown, un attaquant peut demander un SMS
 *   toutes les secondes et ruiner le budget SMS du tenant — et harceler le
 *   numéro cible. 60 s par défaut.
 *
 * Le code est aussi **usage unique** : `consumed_at` le désactive après succès,
 * et un code déjà consommé n'est jamais réutilisable même dans sa fenêtre.
 *
 * @property int $id
 * @property string $company_id
 * @property int $request_id
 * @property string $phone_e164
 * @property string $code_hash
 * @property int $attempts
 * @property int $max_attempts
 * @property Carbon $expires_at
 * @property Carbon|null $cooldown_until
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PageVerificationCode extends Model
{
    use BelongsToCompany;

    /** Durée de vie d'un code (minutes). */
    public const TTL_MINUTES = 10;

    /** Nombre de tentatives avant invalidation définitive. */
    public const MAX_ATTEMPTS = 5;

    /** Délai minimal entre deux envois vers le même numéro (secondes). */
    public const COOLDOWN_SECONDS = 60;

    public $timestamps = false;

    protected $table = 'page_verification_codes';

    protected $fillable = [
        'company_id',
        'request_id',
        'phone_e164',
        'code_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'cooldown_until',
        'consumed_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    /**
     * Génère un code à 6 chiffres (cryptographiquement sûr).
     *
     * `random_int` et non `rand`/`mt_rand` : `mt_rand` est déterministe à
     * partir de sa graine et jamais adapté à un secret.
     */
    public static function generatePlainCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Empreinte d'un code, liée au dossier.
     *
     * Le `request_id` entre dans le message HMAC : la même empreinte ne peut
     * pas être rejouée sur un autre dossier, même si l'attaquant connaît
     * `APP_KEY`-dérivée d'un code observé.
     */
    public static function hashCode(string $plainCode, int $requestId): string
    {
        return hash_hmac('sha256', $requestId.':'.$plainCode, self::hmacKey());
    }

    private static function hmacKey(): string
    {
        $key = (string) config('app.key');

        // `base64:` préfixé par Laravel : on décode pour utiliser les 32 octets
        // réels plutôt que leur représentation ASCII.
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $key !== '' ? $key : 'pages-verification-fallback-key';
    }

    public function isExpired(?Carbon $at = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($at ?? Carbon::now());
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLockedOut(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Un code encore utilisable ?
     */
    public function isUsable(?Carbon $at = null): bool
    {
        $now = $at ?? Carbon::now();

        return ! $this->isConsumed() && ! $this->isExpired($now) && ! $this->isLockedOut();
    }

    /**
     * Le code présenté correspond-il ?
     *
     * Comparaison en temps constant : une comparaison `===` sur des chaînes
     * fuit la position du premier caractère différent, ce qui permet de
     * reconstituer un code caractère par caractère avec assez de mesures.
     */
    public function matches(string $plainCode): bool
    {
        return hash_equals($this->code_hash, self::hashCode($plainCode, (int) $this->request_id));
    }
}
