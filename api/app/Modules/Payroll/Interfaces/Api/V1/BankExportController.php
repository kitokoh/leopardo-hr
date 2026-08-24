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
use App\Support\CompanyBankDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankExportController extends Controller
{
    public function __construct(private readonly DataAccessAuditLogger $auditLogger) {}

    /**
     * GET /api/v1/bank-exports — liste paginée des exports bancaires du
     * tenant (issue #2267, spec openapi-sync US2).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $query = BankExport::query()
            ->where('company_id', $actor->company_id)
            ->with('payrollRun:id,period_start,period_end,status')
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $exports = $query->paginate((int) ($validated['per_page'] ?? 20));

        return BankExportResource::collection($exports)->response();
    }

    /**
     * POST /api/v1/bank-exports — crée un export bancaire pour un run
     * validé/payé (issue #2267, spec openapi-sync US2). Délègue la
     * génération du fichier à GenerateBankExportJob (async, lifecycle
     * pending → generating → generated/failed).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'payroll_run_id' => ['required', 'integer'],
            'format' => ['required', 'in:sepa_xml,ccp_dz,cpa_dz,bna_dz,cnep_dz,edx_dz,virement_ma,csv_generic'],
        ]);

        /** @var PayrollRun|null $payrollRun */
        $payrollRun = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->find($validated['payroll_run_id']);

        if ($payrollRun === null) {
            return response()->json([
                'message' => 'Payroll run not found.',
                'errors' => ['payroll_run_id' => ['Payroll run not found.']],
            ], 422);
        }

        if (! in_array($payrollRun->status, ['validated', 'paid'])) {
            return response()->json(['message' => 'Payroll run must be validated before generating bank export.'], 422);
        }

        $export = BankExport::create([
            'payroll_run_id' => $payrollRun->id,
            'company_id' => $payrollRun->company_id,
            'format' => $validated['format'],
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
            'format' => 'required|in:sepa_xml,ccp_dz,cpa_dz,bna_dz,cnep_dz,edx_dz,virement_ma,csv_generic',
        ]);

        $format = $validated['format'];

        // Issue #2198 — SEPA requires the company's own IBAN (debtor account).
        // Reject synchronously instead of creating a BankExport row that the
        // job would fail on: no placeholder must ever reach the file.
        if ($format === 'sepa_xml') {
            $companyBank = CompanyBankDetails::forCompany((string) $payrollRun->company_id);

            if ($companyBank['iban'] === null) {
                return response()->json([
                    'message' => 'Company IBAN is required for SEPA export. Set companies.metadata.company_iban.',
                    'error' => 'MISSING_COMPANY_IBAN',
                ], 422);
            }
        }

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

        $filePath = $bankExport->file_path;
        if ($filePath === null || ! Storage::disk('local')->exists($filePath)) {
            // #4812 : littéral EN déplacé au catalogue errors.*
            return response()->json(['message' => __('errors.EXPORT_FILE_NOT_FOUND')], 404);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'payroll.bank_export', $bankExport, [
            'format' => $bankExport->format,
        ]);

        return Storage::disk('local')->download($filePath);
    }
}
