<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateBankExportJob;
use App\Jobs\ProcessBulkPaymentJob;
use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendTrialDripEmailJob;
use App\Modules\Marketing\Infrastructure\Jobs\PublishScheduledPostJob;
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
        Log::spy();

        $jobs = [
            new ProcessBulkPaymentJob(1, 1, null),
            new GenerateBankExportJob(1),
            new SendPushNotificationJob(1, 'title', 'body', []),
            new PublishScheduledPostJob(1),
        ];

        // SendTrialDripEmailJob nécessite une Company (SerializesModels) — testé à part.
        $trialCompany = \App\Core\Tenant\Domain\Models\Company::factory()->create(['country' => 'DZ']);
        $jobs[] = new SendTrialDripEmailJob($trialCompany, 3);

        foreach ($jobs as $job) {
            // Ne doit PAS re-lancer — le handler failed() est terminal.
            $job->failed(new RuntimeException('retries exhausted'));
        }

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $channel, array $ctx) => str_contains($channel, 'failed'))
            ->atLeast()->times(4);
    }

    public function test_generate_bank_export_failed_marks_export_failed(): void
    {
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
