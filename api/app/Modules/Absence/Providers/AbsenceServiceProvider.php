<?php

declare(strict_types=1);

namespace App\Modules\Absence\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * PA2-ARCH-002 : ce module n'est qu'une facade HTTP (Interfaces/ uniquement).
 * Les modeles/services metier (Absence, AbsenceType, LeaveBalance,
 * LeaveBalanceLog, AbsenceService) vivent dans App\Modules\Planning\*,
 * proprietaire canonique du domaine absence/conge. Voir api/ARCHITECTURE.md.
 */
class AbsenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun binding : ce module consomme directement les classes Planning\*.
    }

    public function boot(): void
    {
        // Boot Absence module — routes loaded via routes/modules/absence.php
    }
}
