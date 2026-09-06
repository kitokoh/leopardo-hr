<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Feature flags du bounded context BC-27 SHOWCASE (#6865).
 *
 * Le flag tenant `company_showcase` est résolu via `Company::hasFeature()`
 * (JSON `companies.features`, mécanisme Core/Feature — pattern
 * `travelagency`, TRAVEL-102 #6007). Défaut fail-closed : un tenant sans le
 * flag ne voit aucune route de gestion de vitrine.
 */
final class ShowcaseFeatures
{
    /** Activation du module Site vitrine pour un tenant (responsable/PME). */
    public const COMPANY_SHOWCASE = 'company_showcase';
}
