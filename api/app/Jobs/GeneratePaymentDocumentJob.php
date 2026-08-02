<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePaymentDocumentJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $paymentDocumentId)
    {
        $this->onQueue('documents');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var PaymentDocument|null $document */
        $document = PaymentDocument::query()->withoutGlobalScopes()->find($this->paymentDocumentId);

        return $this->resolvedCompanyId = $document?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(CommunicationService $communicationService): void
    {
        /** @var PaymentDocument|null $document */
        $document = PaymentDocument::query()
            ->withoutGlobalScopes()
            ->with(['employee', 'payrollRun', 'paySlip', 'salaryAdvance'])
            ->find($this->paymentDocumentId);

        if ($document === null) {
            Log::warning("GeneratePaymentDocumentJob: document #{$this->paymentDocumentId} not found.");

            return;
        }

        $document->update([
            'status' => PaymentDocument::STATUS_GENERATING,
            'error_message' => null,
        ]);

        // PA2-COMM-010 — Let the employee know their document is being
        // prepared instead of leaving the UI to poll silently. Best-effort:
        // a notification failure must never block PDF generation.
        $this->notifyDocumentStatus($communicationService, $document, 'payment_document_processing');

        try {
            // Tenant context (search_path + current_company) is already active
            // at this point thanks to EnsureTenantContext.
            $company = Company::query()->find($document->company_id);

            $binary = $this->renderPdf($document, $company);
            $filename = $this->filename($document);
            $path = 'payment-documents/'.$document->company_id.'/'.$document->id.'/'.$filename;

            Storage::disk($document->disk ?: 'local')->put($path, $binary);

            $document->update([
                'status' => PaymentDocument::STATUS_AVAILABLE,
                'path' => $path,
                'filename' => $filename,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($binary),
                'generated_at' => now(),
                'error_message' => null,
            ]);

            // PA2-PAY-004: the employee is notified in-app once their
            // payslip/advance-receipt PDF has finished generating and is
            // downloadable, instead of having to poll for it.
            $this->notifyDocumentReady($communicationService, $document);
        } catch (Throwable $e) {
            $document->update([
                'status' => PaymentDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            $this->notifyDocumentStatus($communicationService, $document, 'payment_document_failed');

            report($e);

            throw $e;
        }
    }

    /**
     * Notifies the document's owning employee that their PDF is ready to
     * download. Best-effort: a notification failure must not fail the job
     * or mark an otherwise successfully generated document as failed.
     */
    private function notifyDocumentReady(CommunicationService $communicationService, PaymentDocument $document): void
    {
        $employee = $document->employee;

        if ($employee === null) {
            return;
        }

        try {
            $communicationService->notifyEmployee($employee, 'payment_document_ready', [
                'category' => 'payroll',
                'document_type' => $document->document_type,
                'payment_document_id' => $document->id,
                'payroll_run_id' => $document->payroll_run_id,
                'salary_advance_id' => $document->salary_advance_id,
            ], ['app']);
        } catch (Throwable $e) {
            Log::warning("GeneratePaymentDocumentJob: notification failed for document #{$document->id}: {$e->getMessage()}");
        }
    }

    /**
     * PA2-COMM-010 — Best-effort employee notification for a payment
     * document lifecycle transition (processing / failed). Routed through
     * the same CommunicationService pipeline as every other notification
     * so preferences, quiet hours and audit events stay consistent; a
     * delivery failure here is logged but never rethrown.
     */
    private function notifyDocumentStatus(CommunicationService $communicationService, PaymentDocument $document, string $templateKey): void
    {
        $employee = $document->employee;

        if ($employee === null) {
            return;
        }

        try {
            $communicationService->notifyEmployee($employee, $templateKey, [
                'category' => 'payroll',
                'document_type' => $document->document_type,
                'payment_document_id' => $document->id,
                'payroll_run_id' => $document->payroll_run_id,
                'salary_advance_id' => $document->salary_advance_id,
            ], ['app']);
        } catch (Throwable $e) {
            Log::warning("GeneratePaymentDocumentJob: notification '{$templateKey}' failed for document #{$document->id}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'payment_document:'.$this->paymentDocumentId,
            'queue:documents',
        ];
    }

    private function renderPdf(PaymentDocument $document, ?Company $company): string
    {
        // Queued job — runs outside the HTTP request lifecycle, so the
        // SetLocale middleware never applies here.
        App::setLocale(I18nCatalog::normalizeLocale(
            $document->employee?->preferred_language ?? $company?->language
        ));

        $view = match ($document->document_type) {
            PaymentDocument::TYPE_ADVANCE_RECEIPT => 'pdf.payment-document-advance',
            default => 'pdf.payment-document',
        };

        $pdf = Pdf::loadView($view, [
            'document' => $document,
            'company' => $company,
            'employee' => $document->employee,
            'salaryAdvance' => $document->salaryAdvance,
            'paySlip' => $document->paySlip,
            'payrollRun' => $document->payrollRun,
            'metadata' => $document->metadata ?? [],
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    private function filename(PaymentDocument $document): string
    {
        $suffix = match ($document->document_type) {
            PaymentDocument::TYPE_ADVANCE_RECEIPT => 'advance-'.$document->salary_advance_id,
            PaymentDocument::TYPE_PAYMENT_SLIP => 'payslip-'.$document->pay_slip_id,
            PaymentDocument::TYPE_PAYROLL_SUMMARY => 'payroll-summary-'.$document->payroll_run_id,
            default => 'payment-'.$document->id,
        };

        return $document->document_type.'-'.$suffix.'.pdf';
    }

    public static function dispatchForSalaryAdvance(SalaryAdvance $advance, int $requestedBy): PaymentDocument
    {
        $document = PaymentDocument::query()->create([
            'company_id' => $advance->company_id,
            'employee_id' => $advance->employee_id,
            'salary_advance_id' => $advance->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
            'requested_by' => $requestedBy,
            'metadata' => [
                'amount' => $advance->amount,
                // PA2-PAY-002: snapshot the advance's own currency (set at
                // creation time) so the receipt stays historically accurate
                // even if the tenant's currency setting changes later.
                'currency' => $advance->currency,
                'payment_reference' => $advance->payment_reference,
                'payment_declared_at' => $advance->payment_declared_at?->toIso8601String(),
            ],
        ]);

        self::dispatch($document->id);

        return $document;
    }

    public static function dispatchForPaySlip(PaySlip $paySlip, int $requestedBy): PaymentDocument
    {
        $document = PaymentDocument::query()->create([
            'company_id' => $paySlip->company_id,
            'employee_id' => $paySlip->employee_id,
            'payroll_run_id' => $paySlip->payroll_run_id,
            'pay_slip_id' => $paySlip->id,
            'document_type' => PaymentDocument::TYPE_PAYMENT_SLIP,
            'status' => PaymentDocument::STATUS_PENDING,
            'requested_by' => $requestedBy,
            'metadata' => [
                'net_salary' => $paySlip->net_salary,
                'period_start' => $paySlip->period_start?->toDateString(),
                'period_end' => $paySlip->period_end?->toDateString(),
            ],
        ]);

        self::dispatch($document->id);

        return $document;
    }
}

