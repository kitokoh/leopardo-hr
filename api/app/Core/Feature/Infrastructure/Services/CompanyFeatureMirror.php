<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Tenant\Domain\Models\Company;

/**
 * #8954 / BOS-012 (#8199) — alignement de la vue dérivée `metadata.modules`
 * sur `companies.features`.
 *
 * ## Le défaut corrigé
 *
 * `front/web/src/lib/client-features.ts::explicitToolValue` lit
 * `metadata.modules` **en premier** et fait autorité sur les replis
 * historiques. Or `PATCH /platform/companies/{id}/features` n'écrivait que
 * `features`. Résultat : un opérateur qui coupait une verticale depuis la
 * console plateforme — LE chemin officiel de désactivation — ne changeait
 * **rien** dans l'interface du client. Le serveur voyait la coupure
 * (`hasFeature()` lit `features`), le navigateur non. Client et serveur
 * divergeaient sur l'état d'un module.
 *
 * ## La règle
 *
 * `features` est la **source**, `metadata.modules` une **vue dérivée**. Ce
 * service ne lit jamais `metadata.modules` pour décider quoi écrire — il ne
 * l'écrit qu'à partir de `features`. C'est ce qui empêche une sélection client
 * périmée de ressusciter un module que la plateforme a coupé.
 *
 * ## Ce qu'il ne touche pas
 *
 * Uniquement les clés **miroir** (`metadata_mirror` non nul dans
 * {@see ModuleRegistry}). Les outils horizontaux sans flag plateforme
 * (`employees`, `attendance`, `absences`, `contracts`, `payroll`, `reports`,
 * `marketing`) ne vivent QUE dans `metadata.modules` : les réécrire depuis
 * `features` les effacerait. Ils sont donc préservés à l'identique.
 *
 * Ce service ne sauvegarde pas la company : il pose le `metadata` et laisse
 * l'appelant décider du moment du `save()` (il écrit déjà `features`, deux
 * sauvegardes successives rendraient la bascule visible à moitié appliquée).
 */
final class CompanyFeatureMirror
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * Aligne `metadata.modules` sur `features` pour les clés miroir.
     *
     * La company est modifiée en mémoire (attribut `metadata`), pas persistée.
     *
     * @param  array<string, bool>  $features  la carte `features` effectivement écrite
     * @return array<string, bool> la carte `metadata.modules` résultante
     */
    public function apply(Company $company, array $features): array
    {
        // `metadata` est casté `array` sur le modèle : pas de `is_array()` de
        // garde, PHPStan niveau 8 la rejette comme toujours vraie.
        $metadata = $company->metadata;
        $modules = $metadata['modules'] ?? [];
        $modules = is_array($modules) ? $modules : [];

        foreach ($this->registry->horizontalMirrors() as $clientKey => $flagKey) {
            // Le flag absent de `features` ne doit pas effacer la clé client :
            // une clé non écrite par l'appelant n'est pas une clé à `false`.
            if (! array_key_exists($flagKey, $features)) {
                continue;
            }

            $modules[$clientKey] = (bool) $features[$flagKey];
        }

        $metadata['modules'] = $modules;
        $company->metadata = $metadata;

        /** @var array<string, bool> $modules */
        return $modules;
    }

    /**
     * Prédicat de diagnostic : `metadata.modules` est-il déjà aligné sur
     * `features` pour toutes les clés miroir ? Utile aux tests et à la console
     * de consolidation (`modules:consolidate`), pas au chemin d'écriture.
     *
     * @param  array<string, bool>  $features
     */
    public function isAligned(Company $company, array $features): bool
    {
        $metadata = $company->metadata;
        $modules = $metadata['modules'] ?? [];
        $modules = is_array($modules) ? $modules : [];

        foreach ($this->registry->horizontalMirrors() as $clientKey => $flagKey) {
            if (! array_key_exists($flagKey, $features)) {
                continue;
            }

            if (($modules[$clientKey] ?? null) !== (bool) $features[$flagKey]) {
                return false;
            }
        }

        return true;
    }
}
