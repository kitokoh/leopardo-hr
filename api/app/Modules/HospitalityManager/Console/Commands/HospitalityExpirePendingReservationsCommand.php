<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;
use Illuminate\Console\Command;

/**
 * HOSP-004 (#7946) — Expiration des reservations `pending` dépassées.
 *
 * Les reservations EN LIGNE (vitrine publique HOSP-006) naissent `pending`
 * avec `expires_at = +30 min` : passé ce délai sans confirmation par
 * l'établissement, elles sont annulées et l'inventaire est libéré.
 * Idempotent (re-vérification sous verrou par réservation ; pattern
 * `travel:expire-pending-bookings` #6070) ; schedulée toutes les 5 minutes.
 */
class HospitalityExpirePendingReservationsCommand extends Command
{
    protected $signature = 'hospitality:expire-pending-reservations
        {--company= : Cibler un tenant precis}
        {--limit=500 : nombre max de reservations par passe (defaut 500)}';

    /**
     * PA2-I18N-007 — `$description` est une expression constante : le libellé
     * est la CLÉ du catalogue, remplacée par la traduction dans le
     * constructeur (pattern `travel:expire-adverts`).
     */
    protected $description = 'hospitality.console.expire_pending_description';

    public function __construct(private readonly HospitalityReservationService $reservations)
    {
        parent::__construct();

        $this->description = __('hospitality.console.expire_pending_description');
    }

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn(__('hospitality.console.expire_pending_no_tenant'));

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $total = 0;

        foreach ($companies as $company) {
            $count = $tenantManager->withinTenant(
                $company,
                fn (): int => $this->reservations->expirePending((string) $company->id, $limit),
            );

            if ($count > 0) {
                $this->info(__('hospitality.console.expire_pending_tenant_summary', ['company' => $company->id, 'count' => $count]));
            }

            $total += $count;
        }

        $this->info(__('hospitality.console.expire_pending_total', ['count' => $total]));

        return self::SUCCESS;
    }
}
