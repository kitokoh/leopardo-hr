<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * #7235 — Sélection des outils horizontaux déclarée à l'inscription.
 *
 * Extraite de `ProvisionGuidedTrial` (où elle vivait en méthodes privées) pour
 * être partagée par les DEUX chemins de provisioning. Motif : `POST /trial/signup`
 * accepte et valide `modules[]` / `company_type` quel que soit le workflow,
 * mais seul le chemin guidé les appliquait — une inscription self-service
 * (le parcours du client) perdait donc silencieusement les outils choisis
 * (constat 2026-09-14 : « Comptabilité » cochée à l'inscription, `features`
 * du tenant sans `accounting`, `metadata.modules` absent).
 *
 * Classe volontairement PURE (aucun accès base) : la règle de sélection est
 * testable seule, et les deux appelants gardent la main sur la persistance.
 */
final class HorizontalToolSelection
{
    /**
     * Normalise la sélection : toutes les clés de `Company::HORIZONTAL_TOOLS`
     * sont présentes, `true` pour les outils choisis, `false` pour les autres.
     * Un profil `solo` voit en plus les outils d'ÉQUIPE forcés à `false` — la
     * règle est posée côté serveur, jamais déduite du client.
     *
     * @param  list<string>  $modules
     * @return array<string, bool>|null null quand aucune sélection n'a été fournie
     */
    public function resolve(array $modules, string $companyType): ?array
    {
        if ($modules === []) {
            return null;
        }

        $requested = [];

        foreach ($modules as $module) {
            $key = strtolower(trim((string) $module));

            if ($key !== '' && in_array($key, Company::HORIZONTAL_TOOLS, true)) {
                $requested[$key] = true;
            }
        }

        $selection = [];

        foreach (Company::HORIZONTAL_TOOLS as $tool) {
            $selection[$tool] = isset($requested[$tool]);
        }

        if ($companyType === Company::TYPE_SOLO) {
            foreach (Company::TEAM_TOOLS as $tool) {
                $selection[$tool] = false;
            }
        }

        return $selection;
    }

    /**
     * Miroir des outils choisis vers `features` pour les clés qui existent
     * réellement dans le registre des feature flags (`config/feature-flags.php`).
     * Les autres clés (employees, attendance…) ne sont PAS des flags
     * plateforme : elles vivent dans `metadata.modules`, que le client web
     * consomme directement.
     *
     * @param  array<string, bool>|null  $selection
     * @return array<string, bool>
     */
    public function mirroredFeatures(?array $selection): array
    {
        if ($selection === null) {
            return [];
        }

        $features = [];

        // La correspondance vit dans `Company::HORIZONTAL_TOOL_FEATURES`
        // (source unique, partagée avec l'auto-activation côté tenant) :
        // aucun import cross-BC.
        foreach (Company::HORIZONTAL_TOOL_FEATURES as $selectionKey => $featureKey) {
            if (array_key_exists($selectionKey, $selection)) {
                $features[$featureKey] = $selection[$selectionKey];
            }
        }

        return $features;
    }
}
