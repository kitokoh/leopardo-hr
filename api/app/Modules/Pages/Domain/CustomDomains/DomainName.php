<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains;

/**
 * Normalise et valide un hostname fourni par un tenant (BC PAGES, #8726).
 *
 * ## Pourquoi une classe dédiée plutôt qu'un `filter_var`
 *
 * Un domaine personnalisé est un **point d'entrée non authentifié** : c'est le
 * `Host` de la requête qui décide quelle Page est servie. Chaque approximation
 * ici devient une faille de routage (squat de domaine, collision entre deux
 * tenants, host servant une Page qui n'est pas la sienne).
 *
 * Les règles appliquées, dans cet ordre :
 *
 * 1. **IDNA** — un nom international se saisit en Unicode (`café.sn`), mais le
 *    DNS ne connaît que l'ASCII (`xn--caf-dma.sn`). On convertit **avant** toute
 *    comparaison, sinon `café.sn` et `xn--caf-dma.sn` sont deux clés distinctes
 *    en base et le même domaine peut être revendiqué deux fois.
 * 2. **Suffixes interdits** — un tenant n'offre pas `gmail.com`, ni `leopardo.*`,
 *    ni un TLD à lui. Sans cette liste, la plateforme se ferait usurper sa
 *    propre marque par le premier venu.
 * 3. **Apex / wildcard** — acceptés, mais signalés : un `*.exemple.sn` couvre
 *    tous les sous-domaines et ne peut pas être vérifié par un seul TXT.
 * 4. **Forme** — longueur par label ≤ 63, host ≤ 253, pas de souligné, pas de
 *    label vide, pas de point initial/final, pas d'IP littérale.
 */
final class DomainName
{
    /** Longueur maximale d'un label DNS. */
    public const MAX_LABEL_LENGTH = 63;

    /** Longueur maximale d'un hostname complet. */
    public const MAX_HOST_LENGTH = 253;

    /**
     * TLD / suffixed refusés à la délégation.
     *
     * Choix produit : un tenant ne peut pas apporter un domaine dont le
     * suffixe appartient à la plateforme (`leopardo.*`), ni un domaine de
     * messagerie grand public (une Page « vérifiée » sur `gmail.com` serait un
     * vecteur d'hameçonnage parfait). La liste est courte et explicite : elle
     * est faite pour être relue, pas pour être exhaustiforme.
     *
     * @var list<string>
     */
    public const FORBIDDEN_SUFFIXES = [
        'leopardo.com',
        'leopardo.app',
        'leopardo.io',
        'leopardo.sn',
        'localhost',
        'local',
        'internal',
        'test',
        'example',
        'invalid',
        'gmail.com',
        'googlemail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'icloud.com',
        'protonmail.com',
    ];

    /**
     * Normalise un host saisi par un utilisateur, ou `null` s'il est invalide.
     *
     * Ne lève pas : l'appelant peut vouloir répondre « ce domaine n'est pas
     * valide » sans gérer une exception pour chaque frappe clavier.
     */
    public static function normalize(string $raw): ?string
    {
        $result = self::inspect($raw);

        return $result->isValid() ? $result->host : null;
    }

    /**
     * Analyse complète : host normalisé + raisons de refus.
     */
    public static function inspect(string $raw): DomainInspection
    {
        $errors = [];

        // ── 1. Nettoyage minimal ───────────────────────────────────────────
        // On retire un éventuel schéma et un slash final (`https://x.sn/`) :
        // les gens collent une URL, pas un host. On ne « devine » rien de plus.
        $value = trim($raw);
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? $value;
        $value = rtrim($value, '/');

        if (str_contains($value, '/')) {
            $errors[] = 'un chemin ne fait pas partie d’un domaine';

            return new DomainInspection('', false, false, false, $errors);
        }

        if ($value === '') {
            $errors[] = 'domaine vide';

            return new DomainInspection('', false, false, false, $errors);
        }

        // ── 2. IDNA ────────────────────────────────────────────────────────
        $ascii = $value;
        if (function_exists('idn_to_ascii')) {
            $converted = idn_to_ascii(
                $value,
                IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46,
                $info,
            );

            if ($converted === false) {
                $errors[] = 'nom international non convertible (IDNA)';

                return new DomainInspection('', false, false, false, $errors);
            }

            $ascii = $converted;

            // Le drapeau « pas d'ASCII » évite d'accepter un label qui n'a
            // aucune existence DNS après conversion (ex. `☃.sn`).
            if (isset($info['errors']) && $info['errors'] !== 0) {
                $errors[] = 'nom international invalide (IDNA)';
            }
        }

        $host = strtolower(rtrim($ascii, '.'));

        // ── 3. Longueurs ───────────────────────────────────────────────────
        if (strlen($host) > self::MAX_HOST_LENGTH) {
            $errors[] = sprintf('domaine trop long (max %d)', self::MAX_HOST_LENGTH);
        }

        // ── 4. Forme ───────────────────────────────────────────────────────
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            // Une IP n'est pas un domaine : pas de TLS, pas de TXT par tenant,
            // et le routage par Host deviendrait un accès direct au renderer.
            $errors[] = 'une adresse IP n’est pas un domaine personnalisé';
        }

        if (preg_match('/[^a-z0-9.\-_*]/', $host) === 1) {
            $errors[] = 'caractère interdit dans le domaine';
        }

        /** @var list<string> $labels */
        $labels = explode('.', $host);

        if (count($labels) < 2) {
            $errors[] = 'un domaine doit comporter au moins deux labels';
        }

        $isWildcard = str_starts_with($host, '*.');
        if ($isWildcard) {
            array_shift($labels);
        }

        foreach ($labels as $label) {
            if ($label === '') {
                $errors[] = 'label vide (point double ou final)';

                continue;
            }

            if (strlen($label) > self::MAX_LABEL_LENGTH) {
                $errors[] = sprintf('label « %s » trop long (max %d)', $label, self::MAX_LABEL_LENGTH);
            }

            if (str_contains($label, '_')) {
                $errors[] = 'le souligné n’est pas valide dans un nom d’hôte';
            }

            if (str_starts_with($label, '-') || str_ends_with($label, '-')) {
                $errors[] = 'un label ne peut pas commencer ou finir par un tiret';
            }
        }

        // Un wildcard à un seul niveau restant n'a aucun sens (`*.sn`).
        if ($isWildcard && count($labels) < 2) {
            $errors[] = 'wildcard trop large (au moins deux labels après l’étoile)';
        }

        // ── 5. Suffixes interdits ──────────────────────────────────────────
        foreach (self::FORBIDDEN_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                $errors[] = sprintf('le suffixe « %s » ne peut pas être délégué', $suffix);

                break;
            }
        }

        $isApex = ! $isWildcard && count($labels) === 2;

        return new DomainInspection($host, $errors === [], $isApex, $isWildcard, $errors);
    }

    /**
     * Record TXT attendu pour prouver la propriété du domaine.
     *
     * Le jeton est dérivé du host et d'un secret applicatif : il change si le
     * domaine change et ne peut pas être deviné depuis le seul host, donc
     * personne ne peut préparer un TXT pour un domaine qu'il ne contrôle pas…
     * mais surtout, deux tenants qui visent le même host obtiennent le MÊME
     * jeton — c'est ce qui rend l'unicité vérifiable côté serveur.
     */
    public static function txtRecordName(string $host): string
    {
        // `_leopardo-pages.<host>` : préfixe conventionnel pour les enregistrements
        // de vérification, et il n'entre pas en collision avec `_dmarc`/`_acme`.
        return '_leopardo-pages.'.$host;
    }

    public static function txtRecordValue(string $host, string $secret): string
    {
        return 'leopardo-verify='.hash_hmac('sha256', $host, $secret);
    }

    public static function cnameTarget(string $host): string
    {
        // Le renderer cible ; dérivé du host pour que deux tenants ne partagent
        // pas une entrée identique et pour garder la trace dans les logs DNS.
        return 'pages-custom.'.ltrim(substr(hash('sha256', $host), 0, 12), '.').'.leopardo-pages.app';
    }
}
