<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaySlipResource;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Support\I18nCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            // Issue #2116 — payrollRun porté pour le bloc `compliance`
            // (country_code) exposé par PaySlipResource.
            ->with(['employee:id,first_name,last_name,email', 'payrollRun:id,country_code']);

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

        // Issue #5245 — soldes de congés annuels par employé (1 requête
        // groupée, jamais de N+1) pour le bloc `attendance.leave_balance`.
        $this->attachLeaveBalances($slips->getCollection(), (string) $actor->company_id);

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
            ->with(['employee:id,first_name,last_name,email', 'lines', 'payrollRun:id,country_code'])
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        // Issue #5245 — soldes de congés annuels par employé (1 requête
        // groupée, jamais de N+1) pour le bloc `attendance.leave_balance`.
        $this->attachLeaveBalances($slips->getCollection(), (string) $actor->company_id);

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

        $paySlip->load(['employee:id,first_name,last_name,email', 'lines', 'payrollRun:id,country_code']);

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
            return response()->json(['message' => __('payroll.run_must_be_validated')], 422);
        }

        $slips = $payrollRun->paySlips()
            ->with('employee:id,first_name,last_name,email,preferred_language')
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        /** @var PushNotificationService $pushService */
        $pushService = app(PushNotificationService::class);

        $sent = 0;
        $notified = 0;
        foreach ($slips as $slip) {
            $employee = $slip->employee;
            if ($employee === null || empty($employee->email)) {
                continue;
            }

            $slip->update(['status' => 'sent']);
            $sent++;

            // Issue #3946 — « Envoyer les bulletins » était un no-op : seul le
            // statut passait à `sent`, aucun mail/push n'était déclenché.
            // On notifie l'employé via le canal push existant (PA2-COMM-006,
            // même clés i18n que GeneratePaySlipPdfJob), dans sa propre locale.
            try {
                $previousLocale = App::getLocale();
                App::setLocale(I18nCatalog::normalizeLocale($employee->preferred_language));

                $pushService->sendToEmployee(
                    $employee,
                    (string) trans('notifications.payroll_ready_title'),
                    (string) trans('notifications.payroll_ready_body_with_period', [
                        'period' => $payrollRun->period_end->format('M Y'),
                    ]),
                    [
                        'type' => 'pay_slip_sent',
                        'payroll_run_id' => $payrollRun->id,
                        'pay_slip_id' => $slip->id,
                    ],
                );

                App::setLocale($previousLocale);
                $notified++;
            } catch (Throwable $e) {
                App::setLocale(App::getFallbackLocale());
                Log::warning("PaySlipController::sendSlips — push notification failed for slip #{$slip->id}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'message' => __('payroll.bulletins_sent', ['sent' => $sent, 'notified' => $notified]),
            'sent_count' => $sent,
            'notified_count' => $notified,
            'total_slips' => $slips->count(),
        ]);
    }

    /**
     * Issue #1817 — téléchargement sécurisé du bulletin archivé dans le
     * Cabinet employé (document_type = payslip, read_only). Retourne le
     * document archivé si présent, sinon le PDF généré standard.
     */
    public function document(Request $request, PaySlip $paySlip): Response|StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $isOwner = $paySlip->employee_id === $actor->id && $paySlip->company_id === $actor->company_id;
        $isManager = $paySlip->company_id === $actor->company_id && $actor->isManager();

        if (! $isOwner && ! $isManager) {
            abort(404);
        }

        /** @var CabinetDocument|null $document */
        $document = CabinetDocument::query()
            ->where('employee_id', $paySlip->employee_id)
            ->where('document_type', 'payslip')
            ->where('source_id', $paySlip->id)
            ->latest('id')
            ->first();

        if ($document === null) {
            // Pas encore archivé (run non clôturé) → repli sur le PDF standard.
            return $this->downloadPdf($request, $paySlip, app(PaySlipPdfGenerator::class));
        }

        $disk = Storage::disk($document->disk);
        if (! $disk->exists($document->path)) {
            abort(404, __('errors.ARCHIVED_DOCUMENT_NOT_FOUND'));
        }

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Issue #5245 — attache les soldes de congés annuels (types payés) à
     * chaque bulletin de la collection, en UNE requête groupée (jamais de
     * N+1 par employé).
     *
     * Les soldes ne sont pas persistés sur le bulletin (données vivantes de
     * `leave_balances`) : l'attribut `leave_balance` est porté par le modèle
     * pour la réponse API seulement — {acquired, used, pending, remaining}
     * agrégés sur l'année de la période du bulletin.
     *
     * @param  Collection<int, PaySlip>  $slips
     */
    private function attachLeaveBalances(Collection $slips, string $companyId): void
    {
        if ($slips->isEmpty()) {
            return;
        }

        $employeeIds = $slips->pluck('employee_id')->unique()->values()->all();

        $years = $slips
            ->map(fn (PaySlip $slip): int => (int) $slip->period_start->format('Y'))
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === [] || $years === []) {
            return;
        }

        /** @var Collection<int, LeaveBalance> $rows */
        $rows = LeaveBalance::query()
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('year', $years)
            ->whereHas('absenceType', fn ($query) => $query->where('is_paid', true))
            ->selectRaw('employee_id, SUM(balance) AS remaining, SUM(used) AS used, SUM(pending) AS pending, SUM(balance + used + pending) AS acquired')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        foreach ($slips as $slip) {
            /** @var LeaveBalance|null $row */
            $row = $rows->get($slip->employee_id);

            $slip->setAttribute('leave_balance', $row === null ? null : [
                'acquired' => round((float) $row->getAttribute('acquired'), 2),
                'used' => round((float) $row->getAttribute('used'), 2),
                'pending' => round((float) $row->getAttribute('pending'), 2),
                'remaining' => round((float) $row->getAttribute('remaining'), 2),
            ]);
        }
    }
}
