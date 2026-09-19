<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle HealthManager — HC-001 (issue #7785).
 *
 * Un hôpital ou une clinique privée utilise les capacités communes (HR,
 * Documents, Notifications, Accounting optionnel) et ajoute son propre
 * référentiel de structure (services médicaux, salles, lits, praticiens,
 * spécialités), son registre patients, ses rendez-vous, ses consultations
 * et prescriptions, ses hospitalisations et sa facturation des soins.
 *
 * Les données de santé (dossiers patients, consultations, prescriptions)
 * sont d'une sensibilité MAXIMALE : RBAC strict (le contenu médical n'est
 * jamais visible de la réception ni d'un employé lambda), isolation tenant
 * fail-closed, archivage au lieu de suppression physique.
 *
 * @see docs/specifications/HEALTHMANAGER_SOLUTION.md
 */
final class HealthManagerManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'healthmanager';
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
        return 'Gestion d’hôpitaux et de cliniques privées : services médicaux, praticiens, patients, rendez-vous, consultations, hospitalisations et facturation des soins.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut ; Documents/Notifications sont les modules
        // transversaux requis par la solution (dossiers, convocations).
        return ['rh', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'payroll', 'attendance', 'marketing'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'patients (identité, naissance, groupe sanguin, assurance — PII)',
            'allergies et antécédents médicaux (données de santé)',
            'consultations et diagnostics (données de santé)',
            'prescriptions médicamenteuses (données de santé)',
            'hospitalisations et motifs d’admission (données de santé)',
            'facturation des soins (données financières patient)',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'health.admin' => 'Direction d’établissement : structure, praticiens, patients, activité, facturation',
            'health.practitioner' => 'Praticien : agenda, consultations, prescriptions, patients (lecture)',
            'health.reception' => 'Réception : patients (administratif), rendez-vous, admissions — jamais le contenu médical',
            'health.billing' => 'Facturation : catalogue d’actes, factures et encaissements des soins',
        ];
    }
}
