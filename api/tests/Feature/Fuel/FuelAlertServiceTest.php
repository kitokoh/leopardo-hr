<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\DispatchCommunicationJob;
use App\Modules\FuelStation\Domain\Models\FuelAlertLog;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Notifications et alertes FuelStation — FUEL-019 (issue #5813).
 *
 * Couvre : détection des anomalies (clôture manquante, maintenance en
 * retard), notification des managers via DispatchCommunicationJob,
 * déduplication (rejeu → pas de double notification), audit dans
 * fuel_alert_log, stats par type.
 */
class FuelAlertServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dispatch_detects_and_notifies_missing_closure(): void
    {
        Queue::fake();

        [$company, $manager, $station] = $this->seedOpenSession();

        $service = app(FuelAlertService::class);
        $notified = $service->dispatchDaily($manager);

        $this->assertContains('missing-closure:'.$this->sessionId.':'.now()->toDateString(), $notified);

        Queue::assertPushed(DispatchCommunicationJob::class, function (DispatchCommunicationJob $job): bool {
            return $job->companyId === $this->companyId
                && $job->templateKey === 'fuel_missing_closure'
                && $job->context['category'] === 'fuel';
        });

        $this->assertDatabaseHas('fuel_alert_log', [
            'company_id' => $company->id,
            'alert_type' => 'missing_closure',
            'notified_by' => $manager->id,
        ]);
    }

    public function test_dispatch_is_idempotent_no_double_notification(): void
    {
        Queue::fake();

        [$company, $manager] = $this->seedOpenSession();

        $service = app(FuelAlertService::class);
        $service->dispatchDaily($manager);
        $second = $service->dispatchDaily($manager);

        $this->assertSame([], $second);

        Queue::assertPushed(DispatchCommunicationJob::class, 1);
        $this->assertSame(1, FuelAlertLog::query()->count());
    }

    public function test_dispatch_notifies_overdue_maintenance(): void
    {
        Queue::fake();

        [$company, $manager, $station] = $this->seedTenant();
        FuelMaintenanceTask::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'title' => 'Pompe 1 révision',
            'task_type' => FuelMaintenanceTask::TYPE_PREVENTIVE,
            'status' => FuelMaintenanceTask::STATUS_OPEN,
            'due_at' => now()->subDay(),
            'created_by' => $manager->id,
        ]);

        $service = app(FuelAlertService::class);
        $notified = $service->dispatchDaily($manager);

        $this->assertNotEmpty($notified);
        Queue::assertPushed(DispatchCommunicationJob::class, function (DispatchCommunicationJob $job): bool {
            return $job->templateKey === 'fuel_maintenance_due';
        });
    }

    public function test_no_anomalies_no_notifications(): void
    {
        Queue::fake();

        [$company, $manager] = $this->seedTenant();

        $service = app(FuelAlertService::class);
        $notified = $service->dispatchDaily($manager);

        $this->assertSame([], $notified);
        Queue::assertNotPushed(DispatchCommunicationJob::class);
    }

    public function test_stats_by_type(): void
    {
        [$company, $manager, $station] = $this->seedOpenSession();

        $service = app(FuelAlertService::class);
        $service->dispatchDaily($manager);

        $stats = $service->stats($company->id, now()->subDay()->toIso8601String());

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['by_type']['missing_closure'] ?? 0);
    }

    private int $sessionId = 0;

    private string $companyId = '';

    /**
     * @return array{0: Company, 1: Employee, 2: FuelStation}
     */
    private function seedOpenSession(): array
    {
        [$company, $manager, $station] = $this->seedTenant();
        $this->companyId = (string) $company->id;

        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'opened_by' => $manager->id,
            'opened_at' => now()->subHours(30),
            'opening_balance' => 0,
            'status' => FuelCashSession::STATUS_OPEN,
        ]);
        $this->sessionId = (int) $session->id;

        return [$company, $manager, $station];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: FuelStation}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return [$company, $manager, $station];
    }
}
