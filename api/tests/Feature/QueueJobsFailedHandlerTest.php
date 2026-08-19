<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateBankExportJob;
use App\Jobs\ProcessBulkPaymentJob;
use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendTrialDripEmailJob;
use App\Modules\Marketing\Infrastructure\Jobs\PublishScheduledPostJob;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
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
        // #4382/#4439 : l'API fluide du spy Mockery (withArgs/atLeast/times sur
        // LegacyMockInterface) n'est pas typée pour Larastan. Expectation typée
        // posée AVANT exécution via Log::shouldReceive() (MockInterface) —
        // Mockery vérifie atLeast()->times(5) au teardown.
        Log::shouldReceive('error')->atLeast()->times(5);

        $jobs = [
            new ProcessBulkPaymentJob(1, 1, null),
            new GenerateBankExportJob(1),
            new SendPushNotificationJob(1, 'title', 'body', []),
            new PublishScheduledPostJob(1),
        ];

        // SendTrialDripEmailJob nécessite une Company (SerializesModels) — testé à part.
        /** @var \App\Core\Tenant\Domain\Models\Company $trialCompany */
        $trialCompany = \App\Core\Tenant\Domain\Models\Company::factory()->create(['country' => 'DZ']);
        $jobs[] = new SendTrialDripEmailJob($trialCompany, 3);

        foreach ($jobs as $job) {
            // Ne doit PAS re-lancer — le handler failed() est terminal.
            $job->failed(new RuntimeException('retries exhausted'));
        }
    }

    public function test_generate_bank_export_failed_marks_export_failed(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = \App\Core\Tenant\Domain\Models\Company::factory()->create(['country' => 'DZ']);
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);
        $export = BankExport::query()->create([
            'company_id' => $company->id,
            'payroll_run_id' => $run->id,
            'status' => 'generating',
            'format' => 'csv_generic',
            'total_amount' => 0,
            'transfer_count' => 0,
        ]);

        (new GenerateBankExportJob($export->id))->failed(new RuntimeException('bank api down'));

        $export->refresh();
        $this->assertSame(BankExport::STATUS_FAILED, $export->status);
    }
}
