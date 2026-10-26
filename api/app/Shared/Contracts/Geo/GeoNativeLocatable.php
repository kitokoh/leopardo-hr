<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Geo;

/**
 * #8467 (BC-34 GEO, déviation D2) — entité exposant une colonne
 * `geography(Point,4326)` native, indexée en GIST.
 *
 * ## Pourquoi un contrat SÉPARÉ, optionnel
 *
 * `GeoLocatable` (GEO-04) garantit qu'une entité sait rendre un point ; il ne
 * dit rien de la façon dont ce point est STOCKÉ. Toutes les verticales ne
 * migrent pas en même temps (la spec §D2 prévoit un vertical à la fois), et un
 * modèle sans colonne native doit continuer de fonctionner via le repli
 * Haversine. Ajouter la méthode à `GeoLocatable` aurait donc forcé douze
 * modèles à implémenter une méthode dont dix n'ont pas encore l'usage, et
 * rendu le contrat incapable de distinguer « pas encore migré » de « migré ».
 *
 * En séparant, la recherche proximale pose UNE question — « as-tu une colonne
 * native ? » — et choisit la meilleure stratégie disponible, sans deviner.
 *
 * ## Contrat de nommage
 *
 * La colonne retournée doit être une colonne `geography(Point,4326)` GÉNÉRÉE
 * depuis les colonnes lat/lng (voir migration `#8467`). Elle est donc en
 * lecture seule : une entité ne doit jamais écrire dedans, PostgreSQL
 * recalculerait la valeur à chaque écriture de toute façon.
 */
interface GeoNativeLocatable
{
    /**
     * Nom de la colonne `geography` native, ou `null` si la table n'est pas
     * encore migrée.
     *
     * Retourner `null` est un cas NORMAL et fréquent : c'est le signal que le
     * modèle est en mode Haversine. Une implémentation ne doit jamais mentir en
     * renvoyant un nom de colonne inexistante — la requête échouerait avec une
     * erreur SQL au lieu de dégrader proprement.
     *
     * Peut dépendre de l'existence réelle de la colonne (cache statique), afin
     * qu'une même base puisse servir une migration progressive par vertical.
     */
    public static function geoNativeColumn(): ?string;
}
