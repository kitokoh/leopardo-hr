<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Policies;

use App\Core\Auth\Domain\Models\Employee;

/**
 * TRAVEL-501 (#6071) — Policy des rapports & du dashboard travel.
 *
 * Permission `travel.reports` : ouverte aux rôles opérationnels de l'agence
 * (principal, rh, manager, agent, checkin). Aucun rôle = refus (fail-closed).
 * Enregistrée via `Gate::define('travel.reports', ...)` dans le provider.
 */
final class TravelReportPolicy
{
    public static function authorize(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'agent', 'checkin');
use App\Modules\TravelAgency\Domain\Models\TravelReportExport;

/**
 * TRAVEL-501..507 (#6071..#6077) — Policy des rapports & exports.
 *
 * Permission `travel.reports` — réservée aux rôles manage (principal/rh/
 * manager) : les rapports exposent recettes, PII agrégées et taux
 * d'exploitation (fail-closed, spec §9 Sécurité & RGPD).
 */
class TravelReportPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    public function view(Employee $actor, TravelReportExport $export): bool
    {
        return $this->viewAny($actor) && $export->company_id === $actor->company_id;
    }

    public function export(Employee $actor): bool
    {
        return $this->viewAny($actor);
    }
}
