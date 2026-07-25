<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * PA2-PAY-014 — Bordereaux (bank transfer files) generated outside the HTTP
 * request/response cycle.
 *
 * `BankExportController::generate()` used to build the whole file (SEPA
 * XML, CCP Algerie, CPA/BNA, or generic CSV) synchronously, which blocks the
 * manager's client until every pay slip line has been rendered — this does
 * not scale for large payroll runs. This job mirrors the async
 * `GeneratePaymentDocumentJob`/`GeneratePaySlipPdfJob` pending -> generating
 * -> generated/failed pattern: the controller now creates a `pending`
 * `BankExport` row immediately and dispatches this job to do the actual
 * work on the `documents` queue.
 */
class GenerateBankExportJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $bankExportId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var BankExport|null $export */
        $export = BankExport::query()->withoutGlobalScopes()->find($this->bankExportId);

        return $this->resolvedCompanyId = $export?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(BankExportGenerator $generator): void
    {
        /** @var BankExport|null $export */
        $export = BankExport::query()->withoutGlobalScopes()->find($this->bankExportId);

        if ($export === null) {
            Log::warning("GenerateBankExportJob: BankExport #{$this->bankExportId} not found.");

            return;
        }

        $export->forceFill(['status' => 'generating', 'error_message' => null])->save();

        try {
            /** @var PayrollRun|null $run */
            $run = PayrollRun::query()->withoutGlobalScopes()->find($export->payroll_run_id);

            if ($run === null) {
                throw new \RuntimeException("PayrollRun #{$export->payroll_run_id} not found for BankExport #{$export->id}.");
            }

            $format = $export->format ?? 'csv_generic';
            $content = $generator->generate($run, $format);
            $extension = $generator->fileExtension($format);

            $filePath = sprintf(
                'bank_exports/%s_%s_%s.%s',
                $export->company_id,
                $run->period_start->format('Y_m'),
                $format,
                $extension
            );

            Storage::disk('local')->put($filePath, $content);

            $totalAmount = $run->paySlips()->where('status', 'validated')->sum('net_salary');
            $transferCount = $run->paySlips()->where('status', 'validated')->count();

            $export->forceFill([
                'status' => 'generated',
                'file_path' => $filePath,
                'total_amount' => round((float) $totalAmount, 2),
                'transfer_count' => $transferCount,
                'generated_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $export->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();

            report($e);

            throw $e;
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'bank_export:'.$this->bankExportId,
            'queue:documents',
        ];
    }
}
