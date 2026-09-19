<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle HealthManager — HC-001 (#7785, BC-30).
 *
 * Un hôpital ou une clinique privée utilise les capacités communes (RH,
 * Documents, Notifications, Accounting/Billing recommandés) et ajoute sa
 * structure clinique (services médicaux, salles, lits, praticiens,
 * spécialités — HC-002) et son registre patients (HC-003).
 *
 * Les données de santé sont d'une sensibilité MAXIMALE (RGPD art. 9) :
 * permissions dédiées fail-closed, rétention stricte, isolation tenant
 * absolue — jamais de partage avec le CRM commercial Leopardo.
 *
 * @see docs/specifications/HEALTHMANAGER_SOLUTION.md
 */
final class HealthManagerManifest implements SolutionManifest
{
    public const CODE = 'healthmanager';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'HealthManager';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion d’hôpitaux et cliniques privées : services médicaux, salles, lits, praticiens, spécialités et registre patients.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut ; Documents/Notifications sont les modules
        // transversaux requis par la solution (même socle qu'EduManager).
        return ['rh', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'payroll', 'attendance', 'planning'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'patients (identité, naissance, contacts — PII, données de santé)',
            'dossiers administratifs patients (groupe sanguin, allergies, antécédents — données de santé art. 9 RGPD)',
            'couverture d’assurance santé (n° d’assuré — PII financière)',
            'personne à prévenir (PII de tiers)',
            'praticiens et spécialités (données professionnelles de santé)',
            'occupation des lits (donnée de séjour — santé)',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'health.admin' => 'Administration de l’établissement : structure clinique, praticiens, patients — gestion complète',
            'health.practitioner' => 'Praticien : lecture du registre patients et de la structure clinique',
            'health.reception' => 'Accueil / admissions : gestion du registre patients (dossier administratif)',
            'health.billing' => 'Facturation : couverture d’assurance et éléments facturables',
        ];
    }
}
