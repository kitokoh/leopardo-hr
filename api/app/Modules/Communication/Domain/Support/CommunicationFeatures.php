<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Support;

/**
 * Feature flags du bounded context BC-29 COMMUNICATION (R0, #7685).
 *
 * Le flag tenant `communication` est résolu via `Company::hasFeature()`
 * (JSON `companies.features`, mécanisme Core/Feature — pattern
 * `b2b_catalog`, #6880). Défaut fail-closed : un tenant sans le flag ne
 * voit aucune route du module (403 via `module.communication`).
 * Spec : docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md.
 */
final class CommunicationFeatures
{
    /** Activation du module Communication (boîte mail connectée + IA) pour un tenant. */
    public const COMMUNICATION = 'communication';
}
