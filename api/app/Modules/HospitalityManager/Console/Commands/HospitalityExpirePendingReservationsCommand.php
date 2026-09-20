<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;
use Illuminate\Console\Command;

/**
 * HOSP-004 (#7946) — Expiration des réservations `pending` dépassées.
 *
 * Les réservations EN LIGNE (vitrine publique HOSP-006) naissent `pending`
 * avec `expires_at = +30 min` : passé ce délai sans confirmation par
 * l'établissement, elles sont annulées et l'inventaire est libéré.
 * Idempotent (re-vérification sous verrou par réservation ; pattern
 * `travel:expire-pending-bookings` #6070) ; schedulée toutes les 5 minutes.
 */
class HospitalityExpirePendingReservationsCommand extends Command
{
    protected $signature = 'hospitality:expire-pending-reservations
        {--company= : Cibler un tenant précis}
        {--limit=500 : nombre max de réservations par passe (défaut 500)}';

    protected $description = 'Expire les réservations hospitality pending dépassées : annulation + libération inventaire (HOSP-004/#7946).';

    public function __construct(private readonly HospitalityReservationService $reservations)
    {
        parent::__construct();
    }

    public function handle(TenantManager $tenantManager): int
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('Aucun tenant actif — rien à expirer.');

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
                $this->info("Tenant {$company->id} : {$count} réservation(s) hospitality expirée(s).");
            }

            $total += $count;
        }

        $this->info("Total : {$total} réservation(s) hospitality expirée(s).");

        return self::SUCCESS;
    }
}
