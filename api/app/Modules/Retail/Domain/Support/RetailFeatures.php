<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Support;

/**
 * Feature flags du bounded context BC-17 RETAIL (#7672).
 *
 * Le flag tenant `retail` est résolu via `Company::hasFeature()`
 * (JSON `companies.features`, mécanisme Core/Feature — pattern
 * `b2b_catalog`, BC-28 #6880). Défaut fail-closed : un tenant sans
 * le flag ne voit aucune route de gestion du module Retail.
 */
final class RetailFeatures
{
    /** Activation du module Retail (vendeur générique) pour un tenant. */
    public const RETAIL = 'retail';
}
