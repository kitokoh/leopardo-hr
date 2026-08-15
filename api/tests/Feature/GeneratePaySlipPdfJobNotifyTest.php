<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GeneratePaySlipPdfJob;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use ReflectionMethod;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4010 — GeneratePaySlipPdfJob::notifyEmployee passait un tableau en
 * 2e argument de sendToEmployee(Employee, string $title, string $body,
 * array $data) : TypeError silencieux (catch Throwable) → la notification
 * « bulletin prêt » n'était jamais envoyée. Ce test verrouille l'appel avec
 * les bons types scalaires.
 */
class GeneratePaySlipPdfJobNotifyTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_notify_employee_calls_send_to_employee_with_typed_arguments(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'ar',
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $push = $this->mock(PushNotificationService::class);
        $push->shouldReceive('sendToEmployee')
            ->once()
            ->withArgs(function (Employee $target, string $title, string $body, array $data) use ($run, $employee): bool {
                // L'appel doit passer des scalaires typés — un tableau en
                // 2e position ferait échouer PHP (TypeError) avant ce point.
                return $target->id === $employee->id
                    && $title !== ''
                    && $body !== ''
                    && $data['type'] === 'pay_slip_ready'
                    && $data['payroll_run_id'] === $run->id;
            })
            ->andReturn(1);

        $job = new GeneratePaySlipPdfJob((int) $run->id, (int) $employee->id);
        $method = new ReflectionMethod(GeneratePaySlipPdfJob::class, 'notifyEmployee');
        $method->invoke($job, $push, $employee, $run);

        // Le mock ayant été appelé, Mockery vérifie l'attendu à la fin du test.
    }
}
