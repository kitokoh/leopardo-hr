<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-007 (#8142) — `contracts:alert-expiring` notifie RÉELLEMENT.
 *
 * Avant : le corps de la commande n'était qu'un `Log::info` (aucune
 * notification jamais envoyée) qui écrivait en plus les noms complets des
 * employés dans les logs applicatifs. Ce test exige une preuve observable
 * par un manager : des lignes dans la table canonique `notifications`
 * (celle que lit `GET /notifications`), aux bons destinataires, aux 3 seuils,
 * sans doublon si la commande tourne 2× le même jour (double scheduler), et
 * des logs sans PII.
 */
class ExpiringContractsAlertTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_notifies_principal_and_rh_managers_at_each_threshold(): void
    {
        [$company, $employee, $managers] = $this->fixture();

        $contracts = [
            30 => $this->contract($company->id, $employee->id, 30, 'CT-30'),
            15 => $this->contract($company->id, $employee->id, 15, 'CT-15'),
            7 => $this->contract($company->id, $employee->id, 7, 'CT-07'),
        ];

        $this->assertSame(0, Artisan::call('contracts:alert-expiring'));

        foreach ($contracts as $days => $contract) {
            foreach ($managers as $manager) {
                $this->assertSame(
                    1,
                    $this->notificationCount((int) $manager->id, (int) $contract->id, $days),
                    "Le manager #{$manager->id} doit recevoir l'alerte J-{$days} du contrat #{$contract->id}"
                );
            }
        }

        // 3 seuils × 2 destinataires (principal + rh).
        $this->assertSame(6, Notification::query()->where('type', 'contract_expiring')->count());

        // Le manager hors périmètre (`dept`) et l'employé concerné ne
        // reçoivent rien.
        $this->assertSame(0, Notification::query()->where('employee_id', $this->deptManagerId)->count());
        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());
    }

    public function test_second_run_same_day_does_not_duplicate(): void
    {
        [$company, $employee] = $this->fixture();
        $this->contract($company->id, $employee->id, 30, 'CT-30');

        $this->assertSame(0, Artisan::call('contracts:alert-expiring'));
        $this->assertSame(0, Artisan::call('contracts:alert-expiring'));

        // Le double scheduler (BOS-006A) fait tourner la commande 2×/jour :
        // une seule alerte par (contrat, seuil, date).
        $this->assertSame(2, Notification::query()->where('type', 'contract_expiring')->count());
    }

    public function test_logs_never_contain_employee_names(): void
    {
        [$company, $employee] = $this->fixture();
        $this->contract($company->id, $employee->id, 7, 'CT-07');

        $fullName = trim("{$employee->first_name} {$employee->last_name}");
        $this->assertNotSame('', $fullName);

        $log = Log::spy();

        $this->assertSame(0, Artisan::call('contracts:alert-expiring'));

        $this->assertInstanceOf(\Mockery\MockInterface::class, $log);

        // Aucun log (message OU contexte) ne doit porter le nom de l'employé…
        $log->shouldHaveReceived('info')->withArgs(function (mixed $message, array $context = []) use ($fullName): bool {
            $this->assertStringNotContainsString($fullName, (string) $message);
            $this->assertStringNotContainsString($fullName, (string) json_encode($context));

            return true;
        });

        // …et la ligne d'observabilité attendue est bien émise, en identifiants.
        $log->shouldHaveReceived('info')->withArgs(static function (mixed $message, array $context = []): bool {
            return $message === 'contracts:alert-expiring'
                && ($context['notifications_sent'] ?? null) === 2;
        });
    }

    public function test_inactive_and_off_threshold_contracts_are_ignored(): void
    {
        [$company, $employee] = $this->fixture();

        $this->contract($company->id, $employee->id, 30, 'CT-INACTIVE', 'draft');
        $this->contract($company->id, $employee->id, 45, 'CT-OFF', 'active');

        $this->assertSame(0, Artisan::call('contracts:alert-expiring'));

        $this->assertSame(0, Notification::query()->where('type', 'contract_expiring')->count());
    }

    private int $deptManagerId = 0;

    /**
     * @return array{0: Company, 1: Employee, 2: list<Employee>}
     */
    private function fixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
        ]);
        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);
        /** @var Employee $dept */
        $dept = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'dept',
        ]);
        $this->deptManagerId = (int) $dept->id;

        return [$company, $employee, [$principal, $rh]];
    }

    private function contract(string $companyId, int $employeeId, int $days, string $reference, string $status = 'active'): Contract
    {
        /** @var Contract $contract */
        $contract = Contract::create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'contract_type' => 'cdd',
            'reference' => $reference,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addDays($days)->toDateString(),
            'status' => $status,
        ]);

        return $contract;
    }

    private function notificationCount(int $managerId, int $contractId, int $days): int
    {
        return Notification::query()
            ->where('employee_id', $managerId)
            ->where('type', 'contract_expiring')
            ->where('data->contract_id', (string) $contractId)
            ->where('data->threshold_days', $days)
            ->count();
    }
}
