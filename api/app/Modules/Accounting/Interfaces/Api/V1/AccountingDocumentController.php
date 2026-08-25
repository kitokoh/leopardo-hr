<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Services\DocumentNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * #5223 — API des documents comptables (Comptabilité Phase A) : cycle de vie
 * draft → sent → partiellement payé → payé | annulé (+ overdue), numérotation
 * paramétrable concurrent-safe, avoir lié, irsaliye datée.
 *
 * RBAC : managers principal/comptable (middleware api.manager:principal,comptable)
 * + gardes d'isolation tenant (404) — pattern des modules existants.
 */
class AccountingDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly DocumentNumberingService $numbering,
        private readonly DataAccessAuditLogger $auditLogger,
    ) {}

    /**
     * GET /api/v1/accounting/documents — liste paginée avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        // Statuts d'échéance rafraîchis à la lecture (idempotent, indexé).
        $this->workflow->refreshOverdue((string) $actor->company_id);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'in:'.implode(',', DocumentType::values())],
            'status' => ['nullable', 'string', 'max:30'],
            'contact_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AccountingDocument::query()
            ->with(['contact:id,name', 'lines'])
            ->orderByDesc('issue_date');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['contact_id'])) {
            $query->where('contact_id', $validated['contact_id']);
        }
        if (! empty($validated['from'])) {
            $query->whereDate('issue_date', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('issue_date', '<=', $validated['to']);
        }

        $documents = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json(['data' => $documents->items()]);
    }

    /**
     * POST /api/v1/accounting/documents — création d'un brouillon numéroté.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', DocumentType::values())],
            'contact_id' => ['nullable', 'integer'],
            'project_ref' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'tva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'footer_mentions' => ['nullable', 'string', 'max:2000'],
            'source_document_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $document = $this->workflow->createDraft($validated, (string) $actor->company_id);
        } catch (DocumentWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        // #5273 — trail complet : qui (acteur), quoi (document), quand.
        $this->auditLogger->recordSensitive($request, $actor, 'accounting.document_created', $document);

        return response()->json(['data' => $this->payload($document)], 201);
    }

    /**
     * GET /api/v1/accounting/documents/{document} — détail (lignes + paiements).
     */
    public function show(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);
        $this->workflow->refreshOverdue((string) $document->company_id);

        $fresh = $document->fresh();
        if ($fresh === null) {
            abort(404);
        }

        return response()->json(['data' => $this->payload($fresh)]);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/send — draft → sent.
     */
    public function send(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);

        try {
            $document = $this->workflow->send($document);
        } catch (DocumentWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        $this->auditLogger->recordSensitive($request, $this->actor($request), 'accounting.document_sent', $document);

        return response()->json(['data' => $this->payload($document)]);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/payments — encaissement.
     */
    public function payments(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);
        $actor = $this->actor($request);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:'.implode(',', array_map(static fn ($m): string => $m->value, PaymentMethod::cases()))],
            'received_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $payment = $this->workflow->recordPayment(
                $document,
                (float) $validated['amount'],
                PaymentMethod::from($validated['method']),
                isset($validated['received_at']) ? Carbon::parse($validated['received_at']) : null,
                $validated['reference'] ?? null,
            );
        } catch (DocumentWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'accounting.document_payment', $document, [
            'amount' => (float) $validated['amount'],
        ]);

        return response()->json(['data' => ['payment' => $payment->toArray()]], 201);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/cancel — annulation motivée.
     */
    public function cancel(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $document = $this->workflow->cancel($document, $validated['reason'] ?? null);
        } catch (DocumentWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        $this->auditLogger->recordSensitive($request, $this->actor($request), 'accounting.document_cancelled', $document);

        return response()->json(['data' => $this->payload($document)]);
    }

    /**
     * POST /api/v1/accounting/documents/{document}/credit-note — avoir lié.
     */
    public function creditNote(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);

        $validated = $request->validate([
            'issue_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $creditNote = $this->workflow->createCreditNote($document, $validated);
        } catch (DocumentWorkflowException $exception) {
            return $this->workflowError($exception);
        }

        $this->auditLogger->recordSensitive($request, $this->actor($request), 'accounting.credit_note_created', $document);

        return response()->json(['data' => $this->payload($creditNote)], 201);
    }

    /**
     * GET /api/v1/accounting/documents/next-number?type=invoice — aperçu du
     * prochain numéro (série configurée), pour l'UI.
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', DocumentType::values())],
        ]);

        return response()->json([
            'data' => [
                'type' => $validated['type'],
                'number' => $this->numbering->nextNumber((string) $actor->company_id, DocumentType::from($validated['type'])),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AccountingDocument $document): array
    {
        return $document->load(['lines', 'payments', 'contact:id,name'])->toArray();
    }

    private function actor(Request $request): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        return $actor;
    }

    private function assertTenant(Request $request, AccountingDocument $document): void
    {
        $actor = $this->actor($request);
        if ((string) $document->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    private function workflowError(DocumentWorkflowException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], 422);
    }
}
