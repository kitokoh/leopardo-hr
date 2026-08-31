<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Notification\Infrastructure\Jobs\SendWeeklyManagerDigestJob;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Digest hebdomadaire manager — Issue #5695.
 *
 * Verrouille :
 *   1. le template `weekly_manager_digest` (config communication) et ses
 *      clés i18n dans les 4 locales ;
 *   2. le job : notifie CHAQUE manager actif du tenant avec le bon contexte
 *      (team_size scope manager) sur le canal email uniquement ;
 *   3. le job : ne fait rien pour une entreprise inactive ;
 *   4. la commande : dispatch un job par entreprise ACTIVE.
 */
class WeeklyManagerDigestTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_template_config_exists(): void
    {
        $template = config('communication.templates.weekly_manager_digest');

        $this->assertIsArray($template);
        $this->assertSame('notifications.weekly_manager_digest_title', $template['title_key']);
        $this->assertSame('notifications.weekly_manager_digest_body', $template['body_key']);
        $this->assertContains('team_size', $template['vars']);
        $this->assertContains('pending_absences', $template['vars']);
    }

    public function test_lang_keys_exist_in_all_locales(): void
    {
        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $lang = require lang_path($locale.'/notifications.php');

            $this->assertArrayHasKey('weekly_manager_digest_title', $lang, $locale);
            $this->assertArrayHasKey('weekly_manager_digest_body', $lang, $locale);
        }
    }

    public function test_job_is_tenant_scoped(): void
    {
        $job = new SendWeeklyManagerDigestJob('company-id');

        $this->assertInstanceOf(TenantScopedJob::class, $job);
        $this->assertSame('company-id', $job->tenantCompanyId());
    }

    public function test_job_notifies_each_active_manager_with_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'active']);
        /** @var Department $dept */
        $dept = Department::query()->create(['name' => 'Dép. Test']);
        $dept->company_id = $company->id;
        $dept->save();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => $dept->id,
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $deptManager->id,
            'department_id' => $dept->id,
        ]);

        /** @var CommunicationService&Mockery\MockInterface $communication */
        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldReceive('notifyEmployee')
            ->twice()
            ->with(
                Mockery::type(Employee::class),
                'weekly_manager_digest',
                Mockery::on(function (array $context): bool {
                    $this->assertArrayHasKey('week_start', $context);
                    $this->assertArrayHasKey('team_size', $context);
                    $this->assertArrayHasKey('present', $context);
                    $this->assertArrayHasKey('pending_absences', $context);
                    $this->assertArrayHasKey('pending_advances', $context);
                    $this->assertArrayHasKey('pending_corrections', $context);

                    return true;
                }),
                ['email']
            );

        $job = new SendWeeklyManagerDigestJob((string) $company->id, '2026-08-24');

        app(TenantManager::class)->withinTenant($company, function () use ($job, $communication): void {
            $job->handle($communication);
        });
    }

    public function test_job_context_team_size_respects_manager_scope(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'active']);
        /** @var Department $dept */
        $dept = Department::query()->create(['name' => 'Dép. Test']);
        $dept->company_id = $company->id;
        $dept->save();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => $dept->id,
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $deptManager->id,
            'department_id' => $dept->id,
        ]);

        $contexts = [];

        /** @var CommunicationService&Mockery\MockInterface $communication */
        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldReceive('notifyEmployee')
            ->twice()
            ->with(
                Mockery::type(Employee::class),
                'weekly_manager_digest',
                Mockery::on(static function (array $context) use (&$contexts): bool {
                    $contexts[] = $context;

                    return true;
                }),
                ['email']
            );

        $job = new SendWeeklyManagerDigestJob((string) $company->id, '2026-08-24');

        app(TenantManager::class)->withinTenant($company, function () use ($job, $communication): void {
            $job->handle($communication);
        });

        $teamSizes = array_map(static fn (array $c): int => $c['team_size'], $contexts);

        // principal/rh → toute l'entreprise (2 managers + 1 employé) ;
        // dept → équipe managée + lui-même (2).
        sort($teamSizes);
        $this->assertSame([2, 3], $teamSizes);
    }

    public function test_job_skips_inactive_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'suspended']);
        Employee::factory()->manager()->create(['company_id' => $company->id]);

        /** @var CommunicationService&Mockery\MockInterface $communication */
        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldNotReceive('notifyEmployee');

        $job = new SendWeeklyManagerDigestJob((string) $company->id, '2026-08-24');

        app(TenantManager::class)->withinTenant($company, function () use ($job, $communication): void {
            $job->handle($communication);
        });
    }

    public function test_command_dispatches_one_job_per_active_company(): void
    {
        Queue::fake();

        /** @var Company $activeA */
        $activeA = Company::factory()->create(['status' => 'active']);
        /** @var Company $activeB */
        $activeB = Company::factory()->create(['status' => 'active']);
        Company::factory()->create(['status' => 'suspended']);

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('manager:weekly-digest', ['--week' => '2026-08-24']);
        $cmd->assertExitCode(0);
        $cmd->run();

        Queue::assertPushed(SendWeeklyManagerDigestJob::class, 2);
        Queue::assertPushed(
            SendWeeklyManagerDigestJob::class,
            static fn (SendWeeklyManagerDigestJob $job): bool => $job->companyId === (string) $activeA->id
        );
        Queue::assertPushed(
            SendWeeklyManagerDigestJob::class,
            static fn (SendWeeklyManagerDigestJob $job): bool => $job->companyId === (string) $activeB->id
        );
    }
}
