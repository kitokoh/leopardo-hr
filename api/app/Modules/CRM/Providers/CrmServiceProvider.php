<?php

declare(strict_types=1);

namespace App\Modules\CRM\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * CRM Client (interne tenant) — provider du module (issue #5707, CRM-V0-03).
 *
 * Squelette DDD ratifié par l'ADR-CRM-DUAL-CONTEXTS : le CRM client est un
 * module tenant-scoped distinct du CRM commercial Leopardo (Platform/
 * Marketing). Aucune logique métier ici — les couches Application/Domain/
 * Infrastructure/Interfaces se remplissent au fil des issues CRM-V0-04+.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind CRM module contracts here (CRM-V0-04+)
    }

    public function boot(): void
    {
        // Boot CRM module — routes loaded via routes/modules/crm.php (CRM-V0-08)
    }
}
