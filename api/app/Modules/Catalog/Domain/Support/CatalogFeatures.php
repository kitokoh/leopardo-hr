<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

/**
 * Feature flags du bounded context BC-28 CATALOG (#6880).
 *
 * Le flag tenant `b2b_catalog` est résolu via `Company::hasFeature()`
 * (JSON `companies.features`, mécanisme Core/Feature — pattern
 * `travelagency`, TRAVEL-102 #6007). Défaut fail-closed : un tenant sans
 * le flag ne voit aucune route de gestion du catalogue.
 */
final class CatalogFeatures
{
    /** Activation du module Catalogue B2B pour un tenant (responsable/usine). */
    public const B2B_CATALOG = 'b2b_catalog';
}
