<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle EduManager — EDU-001.
 *
 * Un établissement scolaire utilise les capacités communes (HR, Documents,
 * Notifications, CRM client recommandé, Marketing admissions recommandé,
 * Accounting optionnel) et ajoute ses propres campus, élèves, responsables
 * légaux, années, classes, évaluations et bulletins (spec §6).
 *
 * Les données scolaires (élèves mineurs, responsables légaux, notes) sont
 * sensibles : permissions et rétention plus strictes qu'un contact CRM
 * ordinaire ; elles restent isolées du CRM commercial Leopardo.
 *
 * @see docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md
 */
final class EduManagerManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'edumanager';
    }

    public function name(): string
    {
        return 'EduManager';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion d’établissements scolaires : campus, élèves, responsables légaux, inscriptions, classes, évaluations et bulletins.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut ; Documents/Notifications sont les modules
        // transversaux requis par la solution (spec §6.2).
        return ['rh', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['crm', 'marketing', 'accounting', 'payroll', 'attendance'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'élèves (mineurs — données personnelles)',
            'responsables légaux (PII)',
            'notes et évaluations',
            'présence scolaire',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'edu.admin' => 'Administration scolaire : campus, élèves, inscriptions, années, classes',
            'edu.teacher' => 'Enseignant : classes affectées, évaluations, notes, présence',
            'edu.guardian' => 'Responsable légal : élèves autorisés uniquement',
            'edu.fees' => 'Frais scolaires : tarifs, facturation, encaissements, écritures comptables (EDU-016)',
            'edu.admissions.campaigns' => 'Marketing admissions : relances consenties et opt-out (EDU-015)',
        ];
    }
}
