<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BankExportResource;
use App\Jobs\GenerateBankExportJob;
use App\Modules\Payroll\Domain\Models\BankExport;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankExportController extends Controller
{
    public function __construct(private readonly DataAccessAuditLogger $dataAccessAuditLogger) {}

    public function generate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if (! in_array($payrollRun->status, ['validated', 'paid'])) {
            return response()->json(['message' => 'Payroll run must be validated before generating bank export.'], 422);
        }

        $validated = $request->validate([
            'format' => 'required|in:sepa_xml,ccp_dz,virement_ma,csv_generic',
        ]);

        $format = $validated['format'];

        // PA2-PAY-014: the file itself (SEPA XML / CCP Algerie / CPA/BNA /
        // CSV) is never rendered inside the HTTP request anymore — it does
        // not scale for large payroll runs. Create a `pending` BankExport
        // row immediately and let GenerateBankExportJob (queue `documents`)
        // do the actual work, mirroring GeneratePaymentDocumentJob's
        // pending -> generating -> generated/failed lifecycle.
        $export = BankExport::create([
            'payroll_run_id' => $payrollRun->id,
            'company_id' => $payrollRun->company_id,
            'format' => $format,
            'file_path' => null,
            'total_amount' => 0,
            'transfer_count' => 0,
            'status' => BankExport::STATUS_PENDING,
        ]);

        GenerateBankExportJob::dispatch($export->id);

        return (new BankExportResource($export))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request, BankExport $bankExport): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($bankExport->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $bankExport->load('payrollRun:id,period_start,period_end,status');

        return (new BankExportResource($bankExport))->response();
    }

    public function download(Request $request, BankExport $bankExport): StreamedResponse|JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($bankExport->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if ($bankExport->status !== BankExport::STATUS_GENERATED && $bankExport->status !== BankExport::STATUS_SENT && $bankExport->status !== BankExport::STATUS_CONFIRMED) {
            return response()->json([
                'message' => $bankExport->status === BankExport::STATUS_FAILED
                    ? 'Bank export generation failed: '.($bankExport->error_message ?? 'unknown error.')
                    : 'Bank export is still being generated. Please try again shortly.',
                'status' => $bankExport->status,
            ], 409);
        }

        if ($bankExport->file_path === null || ! Storage::disk('local')->exists($bankExport->file_path)) {
            return response()->json(['message' => 'Export file not found.'], 404);
        }

        $this->dataAccessAuditLogger->record($request, $actor, 'hr_data.bank_export_downloaded', $bankExport);

        return Storage::disk('local')->download($bankExport->file_path);
    }
}
