<?php

declare(strict_types=1);

namespace App\Core\Feature\Domain;

/**
 * BOS-012 (#8199) — dérivation du miroir `metadata.modules` à partir des flags
 * plateforme (`companies.features`).
 *
 * Contexte (#8199) : les deux cartes sont écrites par des chemins distincts
 * depuis des années, d'où des désynchronisations historiques (#7235, #7432).
 * La cible de l'issue : `metadata.modules` devient une **vue dérivée** de
 * `features`, jamais une source. Cette classe porte cette dérivation, et elle
 * est volontairement **pure** : aucune base, aucune écriture, aucun service
 * résolu — elle prend deux cartes et rend la troisième.
 *
 * ⚠️ **Pourquoi elle est partagée avec le diagnostic.** `ModuleConsolidationDiff`
 * (le rapport de `modules:consolidate`) et l'écriture unifiée appellent
 * désormais les MÊMES fonctions ({@see targetValue()}, {@see boolOrNull()}).
 * C'est le point qui rend le diagnostic opposable : si le rapport annonçait un
 * écart que l'écriture ne corrige pas — ou l'inverse — le `--apply` bouclerait
 * sans jamais converger, ou pire, écrirait une valeur que personne n'a mesurée.
 * Une règle dupliquée finit toujours par diverger ; ici il n'y en a qu'une.
 *
 * Périmètre : uniquement les clés **miroir** (`metadata_mirror` non nul dans
 * {@see ModuleRegistry}). Les outils sans flag plateforme (`employees`,
 * `attendance`, `absences`, `contracts`, `payroll`, `reports`, `marketing`) ne
 * vivent QUE dans `metadata.modules` : ils ne sont pas des dérivations, donc
 * ils sont **recopiés tels quels** et jamais recalculés — les supprimer
 * éteindrait des outils réels, les recalculer inventerait une source.
 */
final class ModuleMirror
{
    /**
     * Une carte JSON peut porter autre chose qu'un booléen (legacy : `1`,
     * `"true"`, `null`). Seul un booléen PHP est une déclaration ; tout le
     * reste est traité comme « non déclaré » plutôt que converti — une
     * conversion silencieuse (`(bool) "false"` vaut `true`) fabriquerait un
     * écart fantôme et ferait écrire une valeur fausse en mode apply.
     */
    public static function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * Valeur CIBLE d'une clé miroir : **le flag plateforme gagne**, à défaut le
     * défaut versionné du registre.
     *
     * C'est la règle de résolution de #8199 — `metadata.modules` est une vue
     * dérivée, pas une source — et elle évite qu'une sélection client
     * historique ressuscite un module que la console plateforme a désactivé
     * (kill switch d'entitlement).
     */
    public static function targetValue(?bool $flagValue, bool $default): bool
    {
        return $flagValue ?? $default;
    }

    /**
     * Vue dérivée de `metadata.modules` pour UNE company.
     *
     * Deux passes, et l'ordre compte :
     *   1. les clés **non-miroir** sont recopiées à l'identique (aucune donnée
     *      n'est perdue — y compris une carte malformée à clés numériques) ;
     *   2. les clés **miroir déjà déclarées d'un côté au moins** sont posées à
     *      la valeur cible.
     *
     * ⚠️ Une clé miroir déclarée NULLE PART n'est **pas** écrite. C'est
     * délibéré : `Company::moduleSelection()` documente qu'une absence de
     * sélection (`null`) signifie « aucun verrouillage rétroactif — l'interface
     * garde son comportement antérieur ». Poser `crm => false` sur un tenant
     * qui n'a jamais fait de choix transformerait une absence de déclaration en
     * **interdiction explicite** : le client (`client-features.ts` →
     * `explicitToolValue`) lit `metadata.modules` AVANT `features` et
     * verrouillerait le module. La cohérence recherchée par #8199 est celle des
     * clés déclarées ; on ne fabrique pas des déclarations.
     *
     * @param  array<array-key, mixed>  $modules  carte brute `metadata.modules`
     * @param  array<array-key, mixed>  $features  carte brute `companies.features`
     * @param  array<string, string>  $mirrors  clé client => clé de flag
     * @param  callable(string): bool  $defaultFor  défaut versionné du registre
     * @return array<array-key, mixed>
     */
    public static function derived(
        array $modules,
        array $features,
        array $mirrors,
        callable $defaultFor,
    ): array {
        $derived = [];

        foreach ($modules as $key => $value) {
            if (is_string($key) && array_key_exists($key, $mirrors)) {
                continue;
            }

            $derived[$key] = $value;
        }

        foreach ($mirrors as $clientKey => $flagKey) {
            $clientValue = self::boolOrNull($modules[$clientKey] ?? null);
            $flagValue = self::boolOrNull($features[$flagKey] ?? null);

            if ($clientValue === null && $flagValue === null) {
                continue;
            }

            $derived[$clientKey] = self::targetValue($flagValue, $defaultFor($flagKey));
        }

        return $derived;
    }

    /**
     * Les deux cartes portent-elles la MÊME information ? (comparaison
     * insensible à l'ordre des clés, mais stricte sur les valeurs)
     *
     * L'ordre compte : `derived()` range les clés non-miroir d'abord, alors que
     * `metadata.modules` peut les porter entrelacées. Une comparaison `===`
     * directe conclurait « non convergé » sur des cartes sémantiquement
     * identiques, et `--apply` réécrirait une ligne à chaque exécution — une
     * écriture qui ne converge pas est indistinguable d'un échec.
     *
     * La comparaison reste STRICTE sur les valeurs : `1` et `true` ne sont pas
     * égaux, donc une carte legacy non booléenne est bien normalisée.
     *
     * @param  array<array-key, mixed>  $a
     * @param  array<array-key, mixed>  $b
     */
    public static function isSameMap(array $a, array $b): bool
    {
        $left = $a;
        $right = $b;

        ksort($left);
        ksort($right);

        return $left === $right;
    }

    /**
     * Alias historique de {@see isSameMap()} : « la vue dérivée est-elle déjà
     * celle qu'on écrirait ? ». Conservé comme nom parlant au point d'appel.
     *
     * @param  array<array-key, mixed>  $modules
     * @param  array<array-key, mixed>  $derived
     */
    public static function isConverged(array $modules, array $derived): bool
    {
        return self::isSameMap($modules, $derived);
    }
}
