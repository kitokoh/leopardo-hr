<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateBankExportJob;
use App\Jobs\ProcessBulkPaymentJob;
use App\Jobs\SendPushNotificationJob;
use App\Modules\Payroll\Domain\Models\BankExport;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4205 — chaque job critique définit `failed(Throwable $e)` : l'épuisement
 * des retries est visible (log d'alerte + état entité), jamais silencieux.
 */
class QueueJobsFailedHandlerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_failed_handlers_log_without_rethrowing(): void
    {
        // #4382/#4439 : l'API fluide du spy Mockery n'est pas typée (withArgs/
        // atLeast/times sur LegacyMockInterface) — on pose l'expectation typée
        // via Log::shouldReceive() (MockInterface) avant l'exécution.
        Log::shouldReceive('error')->atLeast()->times(3);
        $log = Log::spy();

        $jobs = [
            new ProcessBulkPaymentJob(1, 1, null),
            new GenerateBankExportJob(1),
            new SendPushNotificationJob(1, 'title', 'body', []),
        ];

        foreach ($jobs as $job) {
            // Ne doit PAS re-lancer — le handler failed() est terminal.
            $job->failed(new RuntimeException('retries exhausted'));
        }

        $log->shouldHaveReceived('error');
    }

    public function test_generate_bank_export_failed_marks_export_failed(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = \App\Core\Tenant\Domain\Models\Company::factory()->create(['country' => 'DZ']);
        $export = BankExport::query()->create([
            'company_id' => $company->id,
            'payroll_run_id' => 1,
            'status' => 'generating',
            'format' => 'csv',
            'total_amount' => 0,
            'transfer_count' => 0,
        ]);

        (new GenerateBankExportJob($export->id))->failed(new RuntimeException('bank api down'));

        $export->refresh();
        $this->assertSame(BankExport::STATUS_FAILED, $export->status);
    }
}
