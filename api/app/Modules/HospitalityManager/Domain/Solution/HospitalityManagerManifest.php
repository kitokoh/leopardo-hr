<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Solution;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la solution sectorielle HospitalityManager — HOSP-001 (issue #7943).
 *
 * Une chaîne d'hôtels, une résidence hôtelière ou un gestionnaire de
 * locations utilise les capacités communes (HR, Documents, Notifications,
 * Accounting optionnel) et ajoute son propre référentiel multi-établissements
 * (propriétés, types de chambres, unités), son équipe par établissement,
 * ses réservations (guichet et en ligne sans compte via vitrine publique)
 * et sa gestion locative (baux et loyers).
 *
 * Distinction avec TravelHotel (BC-24) : les hôtels de la verticale Travel
 * sont des partenaires d'une agence de voyage ; ici l'établissement est le
 * tenant lui-même. Aucun couplage.
 *
 * @see docs/specifications/SOLUTION_HOSPITALITY.md
 */
final class HospitalityManagerManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'hospitality';
    }

    public function name(): string
    {
        return 'HospitalityManager';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion d’hôtels, résidences et locations : établissements, inventaire (types de chambres, unités), équipe par site, réservations guichet et en ligne, gestion locative (baux, loyers).';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        // RH est actif par défaut ; Documents/Notifications sont les modules
        // transversaux requis par la solution (contrats de bail, confirmations
        // de réservation).
        return ['rh', 'documents', 'notifications'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'crm', 'payroll', 'attendance', 'restaurant'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return [
            'clients des réservations (identité, coordonnées — PII)',
            'locataires des baux (identité, coordonnées — PII)',
            'réservations et séjours (dates, montants — données commerciales)',
            'loyers et encaissements (données financières locataire)',
        ];
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        return [
            'hospitality.admin' => 'Direction : établissements, inventaire, équipe, réservations, baux et loyers',
            'hospitality.manager' => 'Responsable de site : son établissement (référentiel, équipe, réservations, encaissements)',
            'hospitality.reception' => 'Réception/guichet : réservations, arrivées et départs de son établissement',
        ];
    }
}
