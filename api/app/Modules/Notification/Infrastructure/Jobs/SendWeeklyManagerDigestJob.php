<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Digest hebdomadaire des managers — Issue #5695.
 *
 * Pour chaque manager actif du tenant (`principal`/`rh` → toute
 * l'entreprise ; `dept` → son département ; `superviseur` → ses équipiers
 * directs), envoie un email transactionnel via
 * `CommunicationService::notifyEmployee(..., ['email'])` (template
 * `weekly_manager_digest`, préférences/heures calmes/quotas respectés,
 * événement `communication_events` tracé).
 *
 * Vit dans le module Notification (hub de communication transverse) :
 * le job n'importe aucun modèle métier (requêtes DB tenant-scopées), il
 * ne viole donc pas l'isolation de modules (issue #5584).
 *
 * Tenant-scoped : le middleware `EnsureTenantContext` restaure le contexte
 * (search_path + current_company) avant l'exécution — un job sans
 * compagnie résolvable est release(30), jamais échoué.
 *
 * File d'attente : `default` (worker Render `leopardo-queue-worker`).
 */
class SendWeeklyManagerDigestJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $companyId,
        public readonly ?string $weekStart = null,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(CommunicationService $communication): void
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);

        if (! $company instanceof Company || $company->status !== 'active') {
            return;
        }

        $weekStart = $this->weekStart ?? now()->startOfWeek()->toDateString();

        $managers = Employee::query()
            ->where('company_id', $company->id)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'dept'])
            ->get();

        foreach ($managers as $manager) {
            $employeeIds = $this->scopedEmployeeIds($manager);

            $context = [
                'week_start' => $weekStart,
                'team_size' => count($employeeIds),
                'present' => $this->countTodayPresent($company->id, $employeeIds),
                'pending_absences' => $this->countPendingByTable('absences', $company->id, $employeeIds),
                'pending_advances' => $this->countPendingByTable('salary_advances', $company->id, $employeeIds),
                'pending_corrections' => $this->countPendingByTable('attendance_correction_requests', $company->id, $employeeIds),
            ];

            $communication->notifyEmployee($manager, 'weekly_manager_digest', $context, ['email']);
        }
    }

    /**
     * Employés visibles par le manager — mêmes règles que
     * `Employee::visibleToManager()` (PA2-SEC-002/003) :
     * principal/rh → toute l'entreprise ; dept → département du manager ;
     * superviseur → équipiers directs (manager_id) + lui-même.
     *
     * @return list<int>
     */
    private function scopedEmployeeIds(Employee $manager): array
    {
        $query = DB::table('employees')
            ->where('company_id', $manager->company_id)
            ->where('status', 'active');

        if ($manager->manager_role === 'dept') {
            $query->where('department_id', $manager->department_id ?? -1);
        } elseif (! in_array($manager->manager_role, ['principal', 'rh'], true)) {
            $query->where(function ($scope) use ($manager): void {
                $scope->where('manager_id', $manager->id)
                    ->orWhere('id', $manager->id);
            });
        }

        /** @var list<int> $ids */
        $ids = $query->pluck('id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countTodayPresent(string $companyId, array $employeeIds): int
    {
        if ($employeeIds === []) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', now()->toDateString())
            ->distinct()
            ->count('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countPendingByTable(string $table, string $companyId, array $employeeIds): int
    {
        if ($employeeIds === []) {
            return 0;
        }

        $query = DB::table($table)
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->whereIn('employee_id', $employeeIds);

        // Les tables tenant sont créées par les migrations tenant ; si une
        // table manque (ancien tenant), le digest reste fonctionnel (0).
        try {
            return $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
