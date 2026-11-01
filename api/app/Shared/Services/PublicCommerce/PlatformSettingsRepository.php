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
            if (! Schema::hasTable('platform_settings')) {
                return null;
            }

            $value = DB::table('platform_settings')
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
}
