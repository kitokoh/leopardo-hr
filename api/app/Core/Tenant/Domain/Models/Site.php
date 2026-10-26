<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Établissement d'exploitation d'un tenant (BOS-T-01 / #8624).
 *
 * Un tenant peut exploiter plusieurs lieux (groupe de restaurants, réseau de
 * stations, chaîne de boutiques). `sites` est donc l'axe de séparation des
 * données métier : chaque opération doit pouvoir être rattachée à un
 * établissement identifiable par un `code` stable au sein de son tenant.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $code Code stable et unique PAR tenant (jamais global)
 * @property string|null $site_type restaurant|station|store|school|office|…
 * @property string $status active|inactive|archived
 * @property string|null $address
 * @property float|null $gps_lat
 * @property float|null $gps_lng
 * @property int $gps_radius_m
 * @property string|null $timezone Fuseau de l'établissement (NULL = celui du tenant)
 * @property string|null $currency Devise de l'établissement (NULL = celle du tenant)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Site extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    /** Statuts acceptés — allowlist fail-closed côté écriture (voir SiteController). */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ARCHIVED];

    protected $table = 'sites';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'site_type',
        'status',
        'address',
        'gps_lat',
        'gps_lng',
        'gps_radius_m',
        'timezone',
        'currency',
        // #8722 — adresse structurée, contacts publics, horaires d'ouverture.
        // Tous optionnels : un établissement créé avant cette extension reste
        // valide, et le géocodage n'est jamais une précondition.
        'country_code',
        'address_line1',
        'address_line2',
        'locality',
        'region',
        'postal_code',
        'access_instructions',
        'public_phone',
        'public_email',
        'public_website',
        'media_ids',
        'opening_hours',
        'opening_hours_exceptions',
    ];

    /**
     * `gps_source` / `gps_verified_at` / `gps_updated_at` ne sont PAS dans
     * `$fillable` : ils décrivent la provenance d'une coordonnée, pas une
     * saisie libre. Les poser passe par les méthodes dédiées
     * (`markCoordinatesManual()`, `markCoordinatesVerified()`), sinon un PATCH
     * de masse pourrait se déclarer « verified » sans preuve.
     */

    // Pas de `$attributes` par défaut : la fixture de test `CreatesMvpSchema`
    // crée une table `sites` minimale (id/company_id/name/timestamps) et un
    // défaut Eloquent serait inclus dans l'INSERT → « column does not exist ».
    // Les valeurs par défaut vivent dans la migration (DEFAULT côté serveur).

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'gps_radius_m' => 'integer',
        // #8722
        'media_ids' => 'array',
        'opening_hours' => 'array',
        'opening_hours_exceptions' => 'array',
        'gps_verified_at' => 'datetime',
        'gps_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Sources admises pour une coordonnée (allowlist, doublée d'un CHECK). */
    public const GPS_SOURCE_MANUAL = 'manual';

    public const GPS_SOURCE_PROVIDER = 'provider';

    public const GPS_SOURCE_VERIFIED = 'verified';

    public const GPS_SOURCES = [
        self::GPS_SOURCE_MANUAL,
        self::GPS_SOURCE_PROVIDER,
        self::GPS_SOURCE_VERIFIED,
    ];

    /** Jours ISO : 1 = lundi … 7 = dimanche. */
    public const ISO_WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

    /**
     * Établissements actifs uniquement — un établissement archivé ne doit plus
     * apparaître dans les sélecteurs d'exploitation.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Résolution par code DANS le tenant courant.
     *
     * Le `code` n'est unique que par tenant (`sites_company_code_uidx`) : un
     * `where('code', …)` non tenant-scopé retournerait l'établissement d'un
     * autre client. Le scope `BelongsToCompany` filtre déjà, cette méthode
     * ajoute la normalisation (casse/espaces) attendue par les imports.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }

    // ─────────────────────────────────────────────────────────────────────
    // #8722 — Adresse : structuré d'abord, repli sur le texte historique
    // ─────────────────────────────────────────────────────────────────────

    /**
     * L'adresse affichable.
     *
     * RÈGLE DE COMPATIBILITÉ, et c'est le point délicat de l'extension : les
     * lignes antérieures n'ont que `address` (texte libre). On ne backfill pas
     * — découper un texte libre en lignes produit des résultats faux. On
     * affiche le structuré **s'il est renseigné**, sinon on retombe sur le
     * texte historique, exactement comme avant. Aucun écran existant ne casse,
     * aucune donnée n'est inventée.
     */
    public function displayAddress(): ?string
    {
        if ($this->address_line1 === null && $this->locality === null) {
            return $this->address;
        }

        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->postal_code,
            $this->locality,
            $this->region,
            $this->country_code,
        ], static fn (?string $p): bool => $p !== null && trim($p) !== '');

        return $parts === [] ? $this->address : implode(', ', $parts);
    }

    /** Vrai si l'adresse structurée est renseignée au minimum (ligne 1 + localité). */
    public function hasStructuredAddress(): bool
    {
        return $this->address_line1 !== null && $this->locality !== null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // #8722 — Provenance des coordonnées
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Coordonnées saisies ou corrigées à la main.
     *
     * Le géocodage n'est jamais une précondition : une position pointée sur
     * une carte par un gérant est un état parfaitement valide, simplement
     * moins fiable qu'une position validée.
     */
    public function markCoordinatesManual(?float $lat = null, ?float $lng = null): void
    {
        if ($lat !== null) {
            $this->gps_lat = $lat;
        }

        if ($lng !== null) {
            $this->gps_lng = $lng;
        }

        $this->gps_source = self::GPS_SOURCE_MANUAL;
        $this->gps_updated_at = \Illuminate\Support\Carbon::now();
    }

    /** Coordonnées issues d'un fournisseur de géocodage. */
    public function markCoordinatesFromProvider(float $lat, float $lng): void
    {
        $this->gps_lat = $lat;
        $this->gps_lng = $lng;
        $this->gps_source = self::GPS_SOURCE_PROVIDER;
        $this->gps_updated_at = \Illuminate\Support\Carbon::now();
    }

    /**
     * Coordonnées confirmées par un humain.
     *
     * La date de vérification est posée dans le même geste : la contrainte
     * `sites_gps_verified_requires_date` refuse un point « verified » sans
     * date, donc un appel qui l'omettrait échouerait au flush.
     */
    public function markCoordinatesVerified(): void
    {
        $this->gps_source = self::GPS_SOURCE_VERIFIED;
        $this->gps_verified_at = \Illuminate\Support\Carbon::now();
    }

    /** Vrai si la position est utilisable pour une carte publique. */
    public function hasCoordinates(): bool
    {
        return $this->gps_lat !== null && $this->gps_lng !== null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // #8722 — Horaires d'ouverture (JAMAIS les horaires RH)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Plages d'un jour ISO (1 = lundi … 7 = dimanche).
     *
     * Structure : `{ "1": [{"opens": "09:00", "closes": "12:00"}, …], … }`.
     * Plusieurs intervalles par jour sont normaux (pause de midi) et une liste
     * VIDE signifie « fermé ce jour-là » — distinct de « horaires non
     * renseignés », qui est l'absence de la clé ou une valeur `null`.
     *
     * @return list<array{opens: string, closes: string}>|null
     */
    public function openingRangesFor(int $isoWeekday): ?array
    {
        if (! in_array($isoWeekday, self::ISO_WEEKDAYS, true)) {
            return null;
        }

        $hours = $this->opening_hours;

        if (! is_array($hours) || ! array_key_exists((string) $isoWeekday, $hours)) {
            return null;
        }

        /** @var list<array{opens: string, closes: string}> $ranges */
        $ranges = $hours[(string) $isoWeekday] ?? [];

        return $ranges;
    }

    /**
     * État d'ouverture à un instant donné.
     *
     * Trois valeurs distinctes et non interchangeables :
     *  - `unknown`  : horaires non renseignés — on ne sait pas, on ne prétend
     *                 pas « fermé » ;
     *  - `closed`   : le jour est renseigné avec une liste vide, ou l'heure
     *                 est hors de toutes les plages ;
     *  - `open`     : l'heure tombe dans une plage.
     *
     * Les heures sont interprétées dans le fuseau de l'établissement quand il
     * est défini. Les plages traversant minuit ne sont pas supposées : une
     * plage `22:00 → 02:00` est signalée `invalid` par `validateOpeningHours()`
     * et doit être déclarée en deux plages ou en exception datée.
     */
    public function openingStateAt(\DateTimeInterface $moment): string
    {
        $hours = $this->opening_hours;

        if (! is_array($hours) || $hours === []) {
            return 'unknown';
        }

        $local = $this->timezone !== null
            ? \Illuminate\Support\Carbon::instance($moment)->setTimezone($this->timezone)
            : \Illuminate\Support\Carbon::instance($moment);

        $day = (int) $local->format('N');
        $now = $local->format('H:i');

        $ranges = $this->openingRangesFor($day);

        if ($ranges === null) {
            return 'unknown';
        }

        foreach ($ranges as $range) {
            if ($now >= $range['opens'] && $now < $range['closes']) {
                return 'open';
            }
        }

        return 'closed';
    }

    /**
     * Valide une structure d'horaires et renvoie les anomalies constatées.
     *
     * On renvoie la LISTE des problèmes au lieu de lever : un import doit
     * pouvoir rapporter toutes les lignes fautives, pas seulement la première.
     *
     * @param  array<string, mixed>  $hours
     * @return list<string>
     */
    public static function validateOpeningHours(array $hours): array
    {
        $errors = [];
        $timePattern = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

        foreach ($hours as $day => $ranges) {
            if (! in_array((int) $day, self::ISO_WEEKDAYS, true) || (string) (int) $day !== (string) $day) {
                $errors[] = "jour ISO invalide: {$day}";
                continue;
            }

            if (! is_array($ranges)) {
                $errors[] = "jour {$day}: liste de plages attendue";
                continue;
            }

            /** @var mixed $range */
            foreach ($ranges as $index => $range) {
                $opens = is_array($range) ? ($range['opens'] ?? null) : null;
                $closes = is_array($range) ? ($range['closes'] ?? null) : null;

                if (! is_string($opens) || preg_match($timePattern, $opens) !== 1) {
                    $errors[] = "jour {$day} plage {$index}: 'opens' doit être HH:MM";
                    continue;
                }

                if (! is_string($closes) || preg_match($timePattern, $closes) !== 1) {
                    $errors[] = "jour {$day} plage {$index}: 'closes' doit être HH:MM";
                    continue;
                }

                if ($opens >= $closes) {
                    // Une plage traversant minuit n'est pas devinée : elle doit
                    // être scindée, ou portée par une exception datée.
                    $errors[] = "jour {$day} plage {$index}: opens ({$opens}) doit précéder closes ({$closes})";
                }
            }
        }

        return $errors;
    }
}
