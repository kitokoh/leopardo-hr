<?php

declare(strict_types=1);

namespace App\Shared\Services\PublicCommerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M0 · Lecture des réglages de plateforme.
 *
 * Le seuil de catégories de la découverte publique est un réglage de
 * PLATEFORME, réglable depuis le Superadmin. Il ne doit jamais être une
 * constante dans le code : une constante obligerait un déploiement pour
 * changer un seuil commercial, et surtout elle rendrait le réglage invisible
 * à l'écran Superadmin que le couloir 16 exige.
 *
 * ## Fail-safe, pas fail-open
 *
 * Si la table n'existe pas (déploiement partiel, base non migrée) ou si la
 * valeur est illisible, on retourne le DÉFAUT (5) — jamais 0. Un seuil à 0
 * afficherait toutes les catégories, y compris celles à une seule Page, ce
 * qui viderait la règle de son sens. Un seuil trop haut masque des
 * catégories : c'est visible et corrigeable ; un seuil à 0 ne se voit pas.
 *
 * ## Pourquoi un dépôt et pas un modèle Eloquent
 *
 * La table est une simple clé/valeur sans relations. Un modèle Eloquent
 * apporterait des casts et des scopes dont personne n'a besoin, et
 * exposerait `update()` à des couches qui ne devraient que lire. Le dépôt
 * n'expose ici que la LECTURE ; l'écriture Superadmin viendra avec son
 * contrôle d'accès, pas avant.
 *
 * Non `final` volontairement : les tests substituent `raw()` pour vérifier la
 * lecture d'un seuil par ville sans dépendre d'une base migrée.
 */
class PlatformSettingsRepository
{
    /** Clé du seuil de catégories de la découverte publique. */
    public const DISCOVERY_CATEGORY_MIN_PAGES = 'discovery.category_min_pages';

    /** Seuil par défaut, celui du cahier des charges du couloir 16. */
    public const DEFAULT_CATEGORY_MIN_PAGES = 5;

    public function __construct() {}

    /**
     * Seuil de catégories applicable à une ville.
     *
     * La valeur stockée peut être :
     *   - un entier : seuil global ;
     *   - un objet `{"default": 5, "by_city": {"Paris": 8}}` : seuil par ville.
     *
     * Une ville absente de `by_city` retombe sur `default`, puis sur la
     * constante. Aucune exception n'est levée : un réglage illisible ne doit
     * pas casser la découverte publique.
     */
    public function categoryMinPages(?string $city = null): int
    {
        $raw = $this->raw(self::DISCOVERY_CATEGORY_MIN_PAGES);

        if ($raw === null) {
            return self::DEFAULT_CATEGORY_MIN_PAGES;
        }

        if (is_numeric($raw)) {
            return $this->sanitize((int) $raw);
        }

        if (! is_array($raw)) {
            return self::DEFAULT_CATEGORY_MIN_PAGES;
        }

        if ($city !== null && $city !== '') {
            $byCity = $raw['by_city'] ?? null;

            if (is_array($byCity)) {
                // Comparaison insensible à la casse : « paris » et « Paris »
                // désignent la même ville pour un visiteur.
                foreach ($byCity as $key => $value) {
                    if (is_string($key) && mb_strtolower($key) === mb_strtolower($city) && is_numeric($value)) {
                        return $this->sanitize((int) $value);
                    }
                }
            }
        }

        $default = $raw['default'] ?? null;

        return is_numeric($default)
            ? $this->sanitize((int) $default)
            : self::DEFAULT_CATEGORY_MIN_PAGES;
    }

    /**
     * Valeur brute décodée, ou null si absente/illisible.
     */
    public function raw(string $key): mixed
    {
        try {
            // `Schema` et `DB` portent déjà la connexion par défaut : injecter
            // un `ConnectionInterface` obligerait à des assertions d'interface
            // que PHPStan refuse à juste titre (`getSchemaBuilder()`,
            // `getName()` n'y sont pas déclarés). Le dépôt reste testable : les
            // tests substituent `raw()`, pas la connexion.
            if (! Schema::hasTable('public.platform_settings')) {
                return null;
            }

            $value = DB::table('public.platform_settings')
                ->where('key', $key)
                ->value('value');

            if ($value === null) {
                return null;
            }

            // PostgreSQL rend du texte JSON ; SQLite aussi. On décode sans
            // supposer le driver.
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }

            return $value;
        } catch (\Throwable) {
            // Un réglage ne doit jamais faire tomber la découverte publique.
            return null;
        }
    }

    /**
     * Un seuil négatif n'a pas de sens ; 0 non plus (il afficherait tout).
     */
    private function sanitize(int $value): int
    {
        return $value >= 1 ? $value : self::DEFAULT_CATEGORY_MIN_PAGES;
    }

    /**
     * Écrit le seuil de catégories (COULOIR 16 — M1 · édition Superadmin).
     *
     * ## Pourquoi l'écriture vit ici et pas dans un service dédié
     *
     * La LECTURE de ce réglage est déjà appelée par la découverte publique
     * (`PublicDiscoveryService`). Ouvrir un second dépôt pour l'écriture
     * donnerait deux vérités sur le même enregistrement — et le jour où le
     * format change (`int` → `{default, by_city}`), il faudrait changer les
     * deux au même instant ou en oublier un.
     *
     * Un seul dépôt, un seul format, une seule validation.
     *
     * ## Ce que la méthode garantit
     *
     * - **Jamais 0** : `sanitize()` ramène toute valeur < 1 au défaut. Un seuil
     *   à 0 afficherait les catégories à une seule Page, vidant la règle de son
     *   sens *sans que personne ne le voie*. Le contrôleur valide aussi, mais
     *   la garantie mécanique est ici : aucune autre couche ne peut écrire 0.
     * - **Trace** : `updated_by` porte l'id du super admin, `audit_logs` porte
     *   l'ancienne ET la nouvelle valeur. Sans l'ancienne valeur, une
     *   modification de seuil serait indétectable en cas de litige.
     * - **Idempotent sur la valeur** : si la valeur ne change pas, aucune
     *   ligne d'audit n'est écrite. Un clic « Enregistrer » sans modification
     *   ne doit pas polluer le journal.
     *
     * @return array{changed: bool, previous: int, current: int}
     */
    public function putCategoryMinPages(int $value, ?int $updatedBy = null): array
    {
        $previous = $this->categoryMinPages();
        $current = $this->sanitize($value);

        if ($current === $previous) {
            // Rien à écrire, rien à tracer : l'absence de changement n'est pas
            // un événement d'audit.
            return ['changed' => false, 'previous' => $previous, 'current' => $current];
        }

        // Table absente (déploiement partiel) : plutôt que de lever — ce qui
        // transformerait un réglage manquant en panne du cockpit — on rapporte
        // l'échec, et le contrôleur répond 503. La découverte publique
        // continuera de servir le défaut.
        if (! Schema::hasTable('public.platform_settings')) {
            throw new \RuntimeException('PLATFORM_SETTINGS_TABLE_MISSING');
        }

        DB::table('public.platform_settings')->upsert(
            [[
                'key' => self::DISCOVERY_CATEGORY_MIN_PAGES,
                // `jsonb` : on encode explicitement, jamais de tableau PHP nu
                // (le driver produirait un littéral ARRAY ou un cast implicite
                // selon la version).
                'value' => json_encode($current, JSON_THROW_ON_ERROR),
                'description' => 'Seuil minimal de Pages publiées pour qu\'une catégorie apparaisse dans la découverte publique.',
                'updated_by' => $updatedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['key'],
            ['value', 'description', 'updated_by', 'updated_at'],
        );

        // Journal d'audit : `company_id` NULL = événement PLATEFORME (voir le
        // docblock d'AuditLog). Ce n'est pas une omission — c'est la
        // convention du dépôt pour tout ce qui n'appartient à aucun tenant.
        \App\Core\Auth\Domain\Models\AuditLog::create([
            'company_id' => null,
            'user_id' => null,
            'action' => 'platform_setting.updated',
            'module' => 'public_commerce',
            // `auditable_id` est un `bigint` : la CLÉ textuelle du réglage n'y
            // a pas sa place (insertion rejetée en 22P02, observé en test).
            // Elle va donc dans `metadata`, qui est du JSON libre — et
            // `auditable_type` reste la classe du dépôt pour retrouver tous
            // les changements de réglages d'un seul filtre.
            'auditable_type' => self::class,
            'auditable_id' => null,
            'old_values' => ['discovery_category_min_pages' => $previous],
            'new_values' => ['discovery_category_min_pages' => $current],
            'metadata' => [
                'setting_key' => self::DISCOVERY_CATEGORY_MIN_PAGES,
                'actor_super_admin_id' => $updatedBy,
            ],
        ]);

        return ['changed' => true, 'previous' => $previous, 'current' => $current];
    }

    /**
     * Réglages exposables au cockpit Superadmin, avec leur valeur effective.
     *
     * `by_city` est renvoyé s'il existe : un seuil par ville posé par un
     * script resterait sinon invisible, et l'écran afficherait 5 alors que
     * Paris est à 8 — un écart que personne ne comprendrait.
     *
     * @return array<string, mixed>
     */
    public function editableSettings(): array
    {
        $raw = $this->raw(self::DISCOVERY_CATEGORY_MIN_PAGES);

        return [
            self::DISCOVERY_CATEGORY_MIN_PAGES => [
                'value' => $this->categoryMinPages(),
                'default' => self::DEFAULT_CATEGORY_MIN_PAGES,
                'by_city' => is_array($raw) && is_array($raw['by_city'] ?? null)
                    ? $raw['by_city']
                    : [],
            ],
        ];
    }
}
