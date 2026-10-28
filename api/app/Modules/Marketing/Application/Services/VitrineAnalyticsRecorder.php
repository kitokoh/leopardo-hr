<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * #7595 — Agrégation des pages vues de la vitrine.
 *
 * ## Pourquoi ce service n'est pas un contrôleur
 *
 * Deux appelants écrivent dans `vitrine_page_views` : l'endpoint d'ingestion
 * public (appelé serveur-à-serveur par la vitrine) et, à terme, une reprise
 * depuis les logs. Un seul point d'écriture garantit que la clé d'unicité
 * (`day, page_path, source, medium, campaign`) est toujours calculée de la même
 * façon — sans quoi le même trafic produirait deux lignes dont une seule serait
 * comptée.
 *
 * ## Ce que la normalisation empêche
 *
 * - **Query string supprimée** : `?utm_source=…` dans le chemin exploserait la
 *   cardinalité (une ligne par campagne) et ferait fuiter des paramètres
 *   arbitraires dans une colonne indexée.
 * - **Slash final et casse neutralisés** : `/pricing` et `/pricing/` sont la
 *   même page ; les compter séparément double artificiellement le top pages.
 * - **Referrer réduit à son hôte** : un referrer complet peut contenir la
 *   requête de recherche de la personne qui a cliqué (`?q=…`). Seul l'hôte est
 *   conservé, et uniquement s'il est externe — sinon chaque page vue depuis la
 *   vitrine créerait une « source » interne parasite.
 */
final class VitrineAnalyticsRecorder
{
    private const TABLE = 'vitrine_page_views';

    /** Profondeur maximale d'un chemin conservé (borne de cardinalité). */
    private const MAX_PATH_LENGTH = 255;

    /**
     * Enregistre une page vue. Idempotent au sens métier : ré-appeler avec la
     * même clé incrémente le compteur au lieu de créer une ligne.
     */
    public function record(
        string $pagePath,
        ?string $source = null,
        ?string $utmMedium = null,
        ?string $utmCampaign = null,
        ?\DateTimeInterface $occurredAt = null,
    ): void {
        $day = ($occurredAt !== null ? \Illuminate\Support\Carbon::instance($occurredAt) : now())
            ->utc()
            ->toDateString();

        $path = self::normalizePath($pagePath);

        $source = self::normalizeAttribution($source);
        $medium = self::normalizeAttribution($utmMedium);
        $campaign = self::normalizeAttribution($utmCampaign);

        // `insertOrIgnore` puis `increment` : deux écritures plutôt qu'un
        // `upsert` parce que l'`upsert` PostgreSQL doit lister toutes les
        // colonnes à jour, y compris `updated_at`, et n'incrémente pas une
        // colonne existante sans expression SQL brute — moins lisible qu'un
        // incrément explicite.
        DB::table(self::TABLE)->insertOrIgnore([
            'day' => $day,
            'page_path' => $path,
            'source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
            'views' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(self::TABLE)
            ->where('day', $day)
            ->where('page_path', $path)
            ->where(fn ($query) => $source === null ? $query->whereNull('source') : $query->where('source', $source))
            ->where(fn ($query) => $medium === null ? $query->whereNull('utm_medium') : $query->where('utm_medium', $medium))
            ->where(fn ($query) => $campaign === null ? $query->whereNull('utm_campaign') : $query->where('utm_campaign', $campaign))
            ->update(['views' => DB::raw('views + 1'), 'updated_at' => now()]);
    }

    /**
     * Normalise un chemin : sans query string, sans fragment, sans slash final,
     * en minuscules. Une entrée vide donne `/` — la page d'accueil est une page
     * réelle, pas une erreur.
     */
    public static function normalizePath(string $raw): string
    {
        $path = trim($raw);

        // Certains appelants envoient une URL complète : on ne garde que le
        // chemin, jamais l'hôte (qui varierait avec les domaines custom).
        if (str_contains($path, '://')) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '/';
        }

        $hashPosition = strpos($path, '#');
        if ($hashPosition !== false) {
            $path = substr($path, 0, $hashPosition);
        }

        $queryPosition = strpos($path, '?');
        if ($queryPosition !== false) {
            $path = substr($path, 0, $queryPosition);
        }

        $path = strtolower($path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = rtrim($path, '/');

        return $path === '' ? '/' : substr($path, 0, self::MAX_PATH_LENGTH);
    }

    /**
     * Réduit un referrer à son hôte, et rien du tout s'il est interne.
     *
     * Un referrer absent, vide, ou pointant sur la vitrine elle-même donne
     * `null` (« accès direct » / « interne »), pas une chaîne vide : sinon
     * `''` et `NULL` créeraient deux groupes distincts dans les agrégats.
     */
    public static function normalizeReferrer(?string $referrer, ?string $appHost = null): ?string
    {
        if ($referrer === null) {
            return null;
        }

        $referrer = trim($referrer);
        if ($referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            // Valeur qui n'est pas une URL (déjà un hôte, ou du bruit) : on la
            // borne au lieu de la jeter, elle reste une source exploitable.
            return self::normalizeAttribution($referrer);
        }

        $host = strtolower($host);

        if ($appHost !== null && strtolower($appHost) !== '' && $host === strtolower($appHost)) {
            return null;
        }

        return substr($host, 0, 120);
    }

    /**
     * Borne une valeur d'attribution à 120 caractères, ou `null` si vide.
     */
    public static function normalizeAttribution(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, 120);
    }
}
