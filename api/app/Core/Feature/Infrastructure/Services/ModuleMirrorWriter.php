<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * BOS-012 (#8199) / #8954 — écriture unifiée de la vue dérivée
 * `metadata.modules` à partir de `companies.features`.
 *
 * Contexte : `front/web/src/lib/client-features.ts` lit `metadata.modules`
 * **en premier** et le traite comme autorité sur les replis historiques. Or
 * `PlatformCompanyFeatureController::update()` n'écrivait que
 * `companies.features` : un opérateur qui coupait un module depuis la console
 * plateforme (le kill switch d'entitlement, chemin officiel de désactivation
 * des verticales) ne changeait donc rien dans l'interface du client. Le
 * serveur voyait la coupure (`hasFeature()` lit `features`), le client non —
 * c'est le moteur de la classe de défauts #7235 / #7432.
 *
 * Règle posée ici, et unique dans le code : **`features` est la source,
 * `metadata.modules` en est la vue dérivée**. Tout écrivain de `features`
 * appelle ce service après avoir persisté, et `features` n'est jamais réécrit
 * à partir de `metadata.modules`.
 *
 * Périmètre volontairement étroit : seules les clés **miroir** du registre
 * (`metadata_mirror` non nul) sont alignées. Les outils horizontaux sans flag
 * plateforme (`employees`, `attendance`, `absences`, `contracts`, `payroll`,
 * `reports`, `marketing`) vivent UNIQUEMENT dans `metadata.modules` : les
 * toucher inventerait une source de vérité qui n'existe pas et éteindrait des
 * outils que la console plateforme ne pilote pas.
 */
final class ModuleMirrorWriter
{
    public function __construct(
        private readonly ModuleRegistryGateway $gateway,
    ) {}

    /**
     * Aligne `metadata.modules` sur `features` pour les clés miroir.
     *
     * Ne persiste pas : l'appelant décide du moment du `save()` (le contrôleur
     * plateforme écrit dans le contexte public posé par
     * `PlatformCompanyLookup`, et un `save()` supplémentaire y ouvrirait une
     * seconde écriture non auditée).
     *
     * @return bool `true` si `metadata.modules` a effectivement changé
     */
    public function align(Company $company): bool
    {
        $mirrors = $this->gateway->horizontalMirrors();

        if ($mirrors === []) {
            return false;
        }

        $features = $company->features;
        $metadata = $company->metadata;
        $modules = is_array($metadata['modules'] ?? null) ? $metadata['modules'] : [];

        $changed = false;

        foreach ($mirrors as $clientKey => $flagKey) {
            // Une clé miroir sans flag déclaré n'a pas de valeur dérivée : on
            // la laisse telle quelle plutôt que de l'éteindre par défaut.
            if (! array_key_exists($flagKey, $features)) {
                continue;
            }

            $target = (bool) $features[$flagKey];

            if (($modules[$clientKey] ?? null) !== $target) {
                $modules[$clientKey] = $target;
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        $metadata['modules'] = $modules;
        $company->metadata = $metadata;

        return true;
    }
}
