<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest de la verticale International Trade OS (TRADE-001, issue #8476,
 * BC-36 TRADE).
 *
 * Identité : code `trade` (feature flag companies.features.trade), industrie
 * `international_trade` (nouvelle case du registre fermé SolutionIndustry —
 * extension par case uniquement, précédent `mobility` BC-35), maturité
 * `pilot`.
 *
 * Couverture : commerce international (import/export) ET B2B domestique
 * (grossistes) — un seul noyau commercial partagé (profils, catalogue,
 * RFQ, offres, commandes, transport, coûts, documents) avec des vues
 * métier acheteur/vendeur (spec docs/specifications/VERTICAL_TRADE_OS.md).
 *
 * Dépendances volontairement nulles en requis : la verticale doit rester
 * activable sur un tenant frais et dégrader gracieusement (leçon du flag
 * `notifications` non enregistré — VTC-01). `crm`, `accounting`,
 * `notifications` et `documents` sont OPTIONNELS : les contreparties
 * s'appuient sur le CRM quand il est actif, les pro forma sur Accounting.
 *
 * Enregistré au `SolutionCatalogue` (clé `trade`) dans TradeServiceProvider —
 * pattern BOS-014 (#8201), même leçon que VtcManifest (#8357).
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\Trade\Providers\TradeServiceProvider
 */
final class TradeManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'trade';
    }

    public function name(): string
    {
        return 'International Trade';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::InternationalTrade;
    }

    public function description(): string
    {
        return 'Commerce international et B2B domestique : sourcing, RFQ multi-fournisseurs, offres commerciales, commandes, cotations transport, calcul de coût rendu, aide à la préparation documentaire et suivi des transactions.';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function optionalModules(): array
    {
        return ['crm', 'accounting', 'notifications', 'documents'];
    }

    /**
     * @return list<string>
     */
    public function sensitiveData(): array
    {
        return ['commercial_terms', 'pricing', 'counterparty_pii', 'trade_documents'];
    }

    /**
     * Permissions / rôles installés à l'activation (personas de la spec
     * §2) — deny-by-default, résolution par `TradeRoleResolver` (pattern
     * VTC-06). Installées aux managers `principal` par
     * `SolutionPermissionInstaller` (BOS-013) ; délégation aux employés
     * via grants `employee_module_grants` portant ces codes.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'trade.admin' => 'Administration Trade : profil commercial, catalogue, paramètres de la verticale',
            'trade.purchase' => 'Achat : demandes de prix (RFQ), invitations fournisseurs, réceptions, commandes d\'achat',
            'trade.sales' => 'Vente : offres commerciales, clients, commandes de vente, pro forma',
            'trade.logistics' => 'Logistique : cotations transport, documents, aide douanière, coûts rendus',
            'trade.reports' => 'Rapports Trade : activité commerciale, conversions, coûts',
        ];
    }
}
