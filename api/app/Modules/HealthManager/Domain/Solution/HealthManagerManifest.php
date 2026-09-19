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
        // HC-003 (#7787) — le registre patients est ACTIF : MRN, identité,
        // naissance/sexe/groupe sanguin, contacts, personne à prévenir,
        // assurance, allergies/antécédents et statut vital. Chiffrement au
        // repos des champs les plus sensibles, archivage sans suppression.
        // HC-004 (#7788) — les rendez-vous sont ACTIFS : lien patient ↔
        // praticien, motif de consultation et présence (no_show).
        return [
            'patients (identité, naissance, contacts — PII, données de santé)',
            'n° de dossier médical (MRN — identifiant de santé par tenant)',
            'dossiers administratifs patients (groupe sanguin, allergies, antécédents — données de santé art. 9 RGPD, chiffrées au repos)',
            'statut vital du patient (décès — donnée de santé)',
            'couverture d’assurance santé (n° d’assuré — PII financière, chiffrée au repos)',
            'personne à prévenir (PII de tiers)',
            'praticiens et spécialités (données professionnelles de santé)',
            'rendez-vous médicaux (lien patient ↔ praticien, motif de consultation, présence — données de santé)',
            'consultations médicales (examen clinique, diagnostic, constantes vitales — données de santé art. 9 RGPD, chiffrées au repos)',
            'ordonnances et lignes de médicaments (posologie — données de santé)',
            'occupation des lits (donnée de séjour — santé)',
            // HC-007 (#7791) — la facturation des soins est ACTIVE : les
            // factures relient un patient à des actes médicaux nommés (la
            // nature du soin transparaît dans les libellés facturés).
            'factures de soins et lignes d’actes (lien patient ↔ actes médicaux, montants — données de santé et financières)',
            'paiements de factures de soins (montants, modes de paiement — PII financière)',
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
