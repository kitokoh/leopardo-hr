<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest de la solution sectorielle Retail (BOS-016, issue #8205).
 *
 * Déclare l'identité, les dépendances modulaires, les données sensibles
 * et les permissions de la verticale commerce de détail / POS.
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\Retail\Providers\RetailServiceProvider
 */
final class RetailManifest implements SolutionManifest
{
    public const CODE = 'retail';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Retail';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::Retail;
    }

    public function description(): string
    {
        return 'Commerce de détail et point de vente : catalogue produits, gestion des stocks, caisse enregistreuse (POS), commandes en ligne et paiements multicanaux.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'delivery', 'marketing', 'payroll'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return ['commandes et transactions', 'données clients et paiements'];
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'retail.pos' => 'Caisse POS : ouverture/fermeture session, encaissement, tickets',
            'retail.catalog' => 'Catalogue : gestion des produits, catégories, prix',
            'retail.stock' => 'Stocks : inventaire, réapprovisionnement, mouvements de stock',
            'retail.orders' => 'Commandes : suivi et traitement des commandes magasin et en ligne',
            'retail.settings' => 'Paramètres commerce : TVA, imprimante ticket, passerelles de paiement',
        ];
    }
}
