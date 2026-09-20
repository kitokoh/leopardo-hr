<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle PharmaManager — PHARMA-001 (#7798).
 *
 * Un fondateur de pharmacie (officine) gère sa structure de A à Z avec les
 * capacités communes (RH, Documents, Notifications, Accounting optionnel) et
 * ajoute son référentiel produits (DCI, formes, dosages), son stock par lots
 * avec péremption, ses achats fournisseurs, ses ventes comptoir et son
 * ordonnancier des produits contrôlés.
 *
 * Les données d'ordonnances (patients, prescripteurs) sont des PII de santé :
 * permissions et rétention plus strictes qu'un contact CRM ordinaire ; elles
 * restent isolées par tenant et ne sont jamais exposées cross-tenant.
 *
 * @see docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md
 */
final class PharmacyManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'pharmacy';
    }

    public function name(): string
    {
        return 'PharmaManager';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion d’officines de pharmacie : référentiel produits (DCI, formes, dosages), stock par lots et péremptions, achats fournisseurs, ventes comptoir, ordonnances et ordonnancier des produits contrôlés.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut sur tout tenant : la verticale n'exige rien
        // de plus pour démarrer (fail-open minimal, spec PHARMA-001).
        return ['rh'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'attendance'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'patients des ordonnances (PII santé)',
            'prescripteurs (PII professionnelles)',
            'délivrances de produits contrôlés (ordonnancier)',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'pharmacy.catalog' => 'Référentiel produits : médicaments, parapharmacie, seuils de stock',
            'pharmacy.stock' => 'Stock : lots, péremptions, mouvements, ajustements d’inventaire',
            'pharmacy.purchasing' => 'Achats : fournisseurs, commandes, réceptions',
            'pharmacy.sales' => 'Ventes comptoir : encaissement, annulations',
            'pharmacy.compliance' => 'Conformité : ordonnances, prescripteurs, ordonnancier des produits contrôlés',
        ];
    }
}
