<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle FuelStation — FUEL-001.
 *
 * Une station-service utilise les capacités communes (HR, Attendance,
 * Documents, Notifications, CRM client recommandé, Accounting recommandé)
 * et ajoute ses propres stations, pompes, cuves, compteurs, shifts,
 * caisses, ventes et incidents (spec §5).
 *
 * @see docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md
 */
final class FuelStationManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'fuel_station';
    }

    public function name(): string
    {
        return 'FuelStation';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion opérationnelle de stations-service : stations, pompes, cuves, shifts, caisses, ventes et incidents.';
    }

    public function requiredModules(): array
    {
        // RH est actif par défaut ; Attendance/Documents/Notifications sont
        // les modules transversaux requis par la solution (spec §5.2).
        return ['rh', 'attendance', 'documents', 'notifications'];
    }

    public function optionalModules(): array
    {
        return ['crm', 'accounting', 'payroll', 'marketing', 'fleet'];
    }

    public function sensitiveData(): array
    {
        return [
            'relevés de compteurs (production opérationnelle)',
            'sessions de caisse (montants)',
            'affectations pompistes (employés)',
        ];
    }

    public function permissions(): array
    {
        return [
            'fuel.manager' => 'Gestion multi-stations (manager principal/rh)',
            'fuel.operator' => 'Pompiste : relevés, shifts, caisse de sa session',
        ];
    }
}
