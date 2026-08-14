<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaySlipResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Interfaces\Api\V1\CabinetDocumentController;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PaySlipController extends Controller
{
    public function __construct(private readonly DataAccessAuditLogger $auditLogger) {}

    /**
     * Liste paginee des bulletins du tenant courant (manager).
     * Evite le pattern N+1 "un GET pay-slips par run" cote SPA.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'payroll_run_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|nullable|string|in:calculated,validated,sent',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|nullable|string|in:period_start,period_end,net_salary,status,id',
            'sort_dir' => 'sometimes|nullable|string|in:asc,desc',
        ]);

        $query = PaySlip::query()
            ->where('company_id', $actor->company_id)
            ->with(['employee:id,first_name,last_name,email']);

        if (! empty($validated['payroll_run_id'])) {
            $runId = (int) $validated['payroll_run_id'];
            $belongs = PayrollRun::query()
                ->whereKey($runId)
                ->where('company_id', $actor->company_id)
                ->exists();
            if (! $belongs) {
                abort(404);
            }
            $query->where('payroll_run_id', $runId);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $sortBy = (string) ($validated['sort_by'] ?? 'period_start');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $slips = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.list', null, [
            'result_count' => $slips->total(),
        ]);

        return PaySlipResource::collection($slips)->response();
    }

    public function indexForRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $slips = $payrollRun->paySlips()
            ->with(['employee:id,first_name,last_name,email', 'lines'])
            ->paginate($request->integer('per_page', 20));

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.list', $payrollRun, [
            'result_count' => $slips->total(),
        ]);

        return PaySlipResource::collection($slips)->response();
    }

    public function show(Request $request, PaySlip $paySlip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paySlip->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $paySlip->employee_id !== $actor->id) {
            abort(403);
        }

        $paySlip->load(['employee:id,first_name,last_name,email', 'lines', 'payrollRun']);

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.detail', $paySlip);

        return (new PaySlipResource($paySlip))->response();
    }

    public function myPaySlips(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:validated,sent',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|nullable|string|in:period_start,period_end,net_salary,status,id',
            'sort_dir' => 'sometimes|nullable|string|in:asc,desc',
        ]);

        $statuses = ! empty($validated['status'])
            ? [(string) $validated['status']]
            : ['validated', 'sent'];
        $sortBy = (string) ($validated['sort_by'] ?? 'period_start');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $slips = PaySlip::where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->whereIn('status', $statuses)
            ->with('payrollRun:id,period_start,period_end,country_code')
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 12));

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.list', null, [
            'scope' => 'self_service',
            'result_count' => $slips->total(),
        ]);

        return PaySlipResource::collection($slips)->response();
    }

    public function myPaySlipDetail(Request $request, PaySlip $paySlip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paySlip->employee_id !== $actor->id || $paySlip->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! in_array($paySlip->status, ['validated', 'sent'])) {
            abort(404);
        }

        $paySlip->load('lines');

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.detail', $paySlip, [
            'scope' => 'self_service',
        ]);

        return (new PaySlipResource($paySlip))->response();
    }

    /**
     * F-09/#1817 — accès au bulletin archivé dans le Cabinet employé.
     *
     * Retourne l'URL de téléchargement sécurisé (authentifiée, tenant-scopée)
     * du `CabinetDocument` `payslip` archivé par `ArchivePaySlipsToCabinetJob`
     * lors de la clôture du run.
     */
    public function myPaySlipDocument(Request $request, PaySlip $paySlip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paySlip->employee_id !== $actor->id || $paySlip->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! in_array($paySlip->status, ['validated', 'sent'])) {
            abort(404);
        }

        $document = CabinetDocument::query()
            ->where('company_id', $this->legacyCompanyKey($paySlip->company_id))
            ->where('employee_id', $paySlip->employee_id)
            ->where('document_type', 'payslip')
            ->where('read_only', true)
            ->where('path', $this->archivePathFor($paySlip))
            ->first();

        if ($document === null) {
            abort(404, 'Bulletin non encore archivé dans le Cabinet. Réessayez après la génération du PDF.');
        }

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.document', $paySlip, [
            'scope' => 'self_service',
            'cabinet_document_id' => $document->id,
        ]);

        return response()->json([
            'data' => [
                'document_id' => $document->id,
                'name' => $document->name,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'download_url' => action(
                    [CabinetDocumentController::class, 'download'],
                    ['cabinetDocument' => $document->id]
                ),
            ],
        ]);
    }

    public function downloadPdf(Request $request, PaySlip $paySlip, PaySlipPdfGenerator $generator): Response
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $isOwner = $paySlip->employee_id === $actor->id && $paySlip->company_id === $actor->company_id;
        $isManager = $paySlip->company_id === $actor->company_id && $actor->isManager();

        if (! $isOwner && ! $isManager) {
            abort(404);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'pay_slip.download', $paySlip, [
            'scope' => $isOwner && ! $isManager ? 'self_service' : 'manager',
        ]);

        $disk = Storage::disk('local');

        if ($paySlip->pdf_path !== null && $disk->exists($paySlip->pdf_path)) {
            $pdfContent = $disk->get($paySlip->pdf_path);
        } else {
            $pdfContent = $generator->generate($paySlip);
        }

        $filename = sprintf('bulletin_%s_%s.pdf',
            $paySlip->employee_id,
            $paySlip->period_start->format('Y_m')
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendSlips(Request $request, PayrollRun $payrollRun): JsonResponse
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
            return response()->json(['message' => 'Le run de paie doit être validé avant envoi.'], 422);
        }

        $slips = $payrollRun->paySlips()
            ->with('employee:id,first_name,last_name,email')
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        $sent = 0;
        foreach ($slips as $slip) {
            if (! empty($slip->employee->email)) {
                $slip->update(['status' => 'sent']);
                $sent++;
            }
        }

        return response()->json([
            'message' => "{$sent} bulletin(s) marqué(s) comme envoyé(s).",
            'sent_count' => $sent,
            'total_slips' => $slips->count(),
        ]);
    }

    /**
     * Chemin d'archivage identique à `ArchivePaySlipsToCabinetJob` :
     * `payslips/{company_id}/{year}/{month}/slip_{employee_id}_{run_id}.pdf`.
     */
    private function archivePathFor(PaySlip $paySlip): string
    {
        $year = $paySlip->period_end?->format('Y') ?? date('Y');
        $month = $paySlip->period_end?->format('m') ?? date('m');

        return sprintf(
            'payslips/%s/%s/%s/slip_%d_%d.pdf',
            $paySlip->company_id,
            $year,
            $month,
            $paySlip->employee_id,
            $paySlip->payroll_run_id
        );
    }

    /**
     * `cabinet_documents.company_id` est une clé historique entière (les
     * tenants modernes portent des UUID) — même convention que
     * `CabinetService::legacyCompanyKey()`.
     */
    private function legacyCompanyKey(string|int|null $companyId): int
    {
        if (is_numeric($companyId)) {
            return (int) $companyId;
        }

        return 0;
    }
}

