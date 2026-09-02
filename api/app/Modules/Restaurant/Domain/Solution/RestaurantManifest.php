<?php

declare(strict_types=1);

namespace App\Modules\Restaurant\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Modules\Restaurant\Domain\Survey\RestaurantSurvey;

/**
 * Manifest de la solution sectorielle Restaurant — REST-001.
 *
 * Un restaurant utilise les capacités communes (RH, Attendance, Documents,
 * Notifications) et ajoute ses propres workflows : service, caisse (POS),
 * réservations, stock, livraison, fidélité et kiosque de pointage.
 *
 * Le manifest décrit le PACK ; le questionnaire de pré-qualification
 * (RestaurantSurvey) détermine, selon les réponses du prospect, quels
 * packages de ce pack lui sont suggérés.
 *
 * @see RestaurantSurvey
 * @see docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md
 * @see docs/architecture/RESTAURANT_SOLUTION_SURVEY.md
 */
final class RestaurantManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'restaurant';
    }

    public function name(): string
    {
        return 'Restaurant';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion opérationnelle de restaurant : équipe, pointage, planning, service, réservations, stock, livraison et fidélité.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut ; Attendance/Documents/Notifications sont
        // les modules transversaux requis (même vocabulaire que FuelStation).
        return ['rh', 'attendance', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return [
            'payroll',
            'planning',
            'accounting',
            'crm',
            'marketing',
            'edge_sync',
            'billing',
        ];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'pointages et horaires des employés',
            'planning des équipes',
            'données de paie (si module activé)',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'restaurant.manager' => 'Gestion du restaurant (manager principal/rh)',
            'restaurant.operator' => 'Service : pointage, réservations et caisse de la session',
        ];
    }
}
