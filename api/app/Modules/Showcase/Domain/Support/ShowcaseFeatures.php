<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Feature flags du bounded context BC-27 SHOWCASE (#6865).
 *
 * Le flag tenant `company_showcase` est resolu via `Company::hasFeature()`
 * (JSON `companies.features`, mecanisme Core/Feature — pattern `travelagency`,
 * TRAVEL-102 #6007, et `b2b_catalog`, BC-28 #6880). Defaut fail-closed : un
 * tenant sans le flag n'a aucun acces au module vitrine.
 */
final class ShowcaseFeatures
{
    /** Activation du site vitrine public pour un tenant (responsable). */
    public const COMPANY_SHOWCASE = 'company_showcase';
}
