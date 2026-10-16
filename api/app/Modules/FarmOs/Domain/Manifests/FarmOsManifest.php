<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Farm OS — Manifest de la verticale agricole (BC-37 AGRICULTURE, mission
 * « Farm OS », spec docs/specifications/SOLUTION_FARM_OS.md).
 *
 * Identité : code `farm_os` (feature flag companies.features.farm_os),
 * industrie `agriculture` (nouvelle case du registre fermé SolutionIndustry),
 * maturité `pilot` — une verticale n'est jamais déclarée `production` avant
 * d'avoir été testée sur un pilote réel.
 *
 * Dépendances volontairement MINIMALES (leçon VTC-01/#8357) : le seul module
 * REQUIS est `rh` (socle des employés, rôles et permissions — Farm OS n'a aucun
 * système de rôles propre). Tout le reste est OPTIONNEL afin que la verticale
 * soit activable sur un tenant frais et dégrade gracieusement : `planning`
 * (tâches), `notifications`, `geo` (géolocalisation des parcelles), `fleet`
 * (matériel roulant), `billing` + `accounting` + `expense` (facturation, devises,
 * coûts — AUCUN système financier parallèle, exigence explicite de la mission),
 * `crm` (acheteurs), `catalog`, `ai` (assistant agricole).
 *
 * Enregistré au `SolutionCatalogue` (clé `farm_os`) dans
 * {@see \App\Modules\FarmOs\Providers\FarmOsServiceProvider} — pattern BOS-014.
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Core\Solutions\SolutionActivator
 */
final class FarmOsManifest implements SolutionManifest
{
    public const CODE = 'farm_os';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Farm OS';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::Agriculture;
    }

    public function description(): string
    {
        return 'Verticale agricole : exploitations et parcelles, cycles culturaux et calendrier des travaux, '
            .'intrants et stock, materiel, cheptel, recoltes et tracabilite, achats et ventes, couts et rentabilite, '
            .'assistant agricole — modules activables au choix de l\'exploitation.';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['rh'];
    }

    /**
     * @return list<string>
     */
    public function optionalModules(): array
    {
        return [
            'planning',
            'notifications',
            'geo',
            'fleet',
            'billing',
            'accounting',
            'expense',
            'crm',
            'catalog',
            'ai',
        ];
    }

    /**
     * @return list<string>
     */
    public function sensitiveData(): array
    {
        return [
            'owner_pii',
            'farm_location',
            'parcel_geometry',
            'financial_data',
            'animal_health',
        ];
    }

    /**
     * Permissions installées à l'activation (deny-by-default, révoquées si la
     * solution est désactivée et non partagées avec une autre solution active).
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'farm.admin' => 'Administration Farm OS : exploitations, activites, outils, unites',
            'farm.manager' => 'Encadrement : parcelles, cycles culturaux, interventions, affectations',
            'farm.worker' => 'Ouvrier agricole : taches, avancement, observations, signalements',
            'farm.stock' => 'Intrants et stock : articles, mouvements, seuils, fournisseurs',
            'farm.equipment' => 'Materiel agricole : compteurs saisis, entretiens, pannes, couts',
            'farm.livestock' => 'Cheptel : animaux, evenements, reproduction, productions, sante animale',
            'farm.sales' => 'Ventes et achats : clients, commandes, factures via Billing',
            'farm.accounting' => 'Couts, imputation et rentabilite (donnees reelles vs estimees)',
            'farm.regulated_inputs' => 'Sortie de produits phytosanitaires ou dangereux (exigence reglementaire)',
        ];
    }
}
