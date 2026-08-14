<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Auth\Access\Response;

/**
 * Issue #1917 — jours fériés (table publique partagée `public_holidays`).
 *
 * Miroir de l'ancien `authorizeWrite` de `PublicHolidayController` :
 *
 *  - SuperAdmin plateforme (guard `super_admin_api`, routes `/api/v1/admin/*`) :
 *    gestion de tous les fériés (nationaux `company_id = null` + entreprises) ;
 *  - manager `principal` (routes tenant `/api/v1/public-holidays`) : uniquement
 *    les fériés d'ENTREPRISE de SA société — les fériés nationaux restent en
 *    lecture seule pour les tenants.
 *
 * Les refus portent le message i18n `errors.FORBIDDEN`, comme les `abort(403)`
 * historiques (statuts et messages préservés).
 */
class PublicHolidayPolicy
{
    public function viewAny(Employee|SuperAdmin $actor): Response
    {
        return $this->canManage($actor);
    }

    public function create(Employee|SuperAdmin $actor): Response
    {
        return $this->canManage($actor);
    }

    public function update(Employee|SuperAdmin $actor, PublicHoliday $holiday): Response
    {
        return $this->canWrite($actor, $holiday);
    }

    public function delete(Employee|SuperAdmin $actor, PublicHoliday $holiday): Response
    {
        return $this->canWrite($actor, $holiday);
    }

    private function canManage(Employee|SuperAdmin $actor): Response
    {
        return ($actor instanceof SuperAdmin || $actor->isPrincipal())
            ? Response::allow()
            : Response::deny(__('errors.FORBIDDEN'));
    }

    private function canWrite(Employee|SuperAdmin $actor, PublicHoliday $holiday): Response
    {
        if ($actor instanceof SuperAdmin) {
            return Response::allow();
        }

        // principal : uniquement les fériés d'entreprise de sa société ;
        // jamais un férié national ni un férié d'une autre entreprise.
        $ownCompanyHoliday = $actor->isPrincipal()
            && $holiday->company_id !== null
            && $holiday->company_id === $actor->company_id;

        return $ownCompanyHoliday
            ? Response::allow()
            : Response::deny(__('errors.FORBIDDEN'));
    }
}
