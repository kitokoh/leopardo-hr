<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Company;
use App\Models\PaymentDocument;
use App\Models\PaySlip;
use App\Models\SalaryAdvance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePaymentDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $paymentDocumentId)
    {
        $this->onQueue('documents');
    }

    public function handle(): void
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

        try {
            $company = Company::query()->find($document->company_id);
            if ($company !== null) {
                app()->instance('current_company', $company);
            }

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
        } catch (Throwable $e) {
            $document->update([
                'status' => PaymentDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            report($e);

            throw $e;
        } finally {
            app()->forgetInstance('current_company');
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
