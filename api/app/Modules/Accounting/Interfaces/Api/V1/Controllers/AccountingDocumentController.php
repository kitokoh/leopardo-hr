<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Services\DocumentNumberingService;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfArchiveService;
use App\Modules\Accounting\Infrastructure\Services\DocumentShareService;
use App\Modules\Accounting\Interfaces\Api\V1\Controllers\Concerns\ResolvesAccountingActor;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\ShareDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    use ResolvesAccountingActor;

    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly DocumentNumberingService $numbering,
        private readonly DataAccessAuditLogger $auditLogger,
        private readonly DocumentShareService $shareService,
        private readonly DocumentPdfArchiveService $pdfArchive,
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
     * POST /api/v1/accounting/documents/{document}/cancel — annulation motivée.
     */
    public function cancel(Request $request, AccountingDocument $document): JsonResponse
    {
        $this->assertTenant($request, $document);

        // #9379 — un document déjà payé ne s'annule pas : il s'écrit un avoir.
        // Le middleware de route ne pouvait pas exprimer cette règle (elle
        // dépend de l'état de la pièce, pas du rôle).
        if ($request->user() !== null && ! $request->user()->can('cancel', $document)) {
            return response()->json([
                'message' => __('accounting.errors.cancel_paid_document'),
            ], 403);
        }

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
     * POST /api/v1/accounting/documents/{document}/share — crée un partage
     * tokenisé du document (#9765).
     *
     * Le modèle `AccountingDocumentShare`, `DocumentShareService` et le portail
     * public existaient déjà ; seule la route de CRÉATION manquait, donc aucun
     * jeton ne pouvait être généré depuis la plateforme et le vendeur ne
     * pouvait jamais envoyer un devis ou une facture à son client.
     *
     * Le jeton n'est renvoyé qu'ICI (création) : l'audit des accès
     * (`ShareAccessController`) ne l'expose jamais.
     */
    public function share(ShareDocumentRequest $request, int $document): JsonResponse
    {
        $actor = $this->actor($request);

        // Isolation tenant fail-closed : scope global BelongsToCompany actif
        // (middleware tenant) → document inconnu ou cross-tenant = 404.
        $model = AccountingDocument::query()->findOrFail($document);

        $validated = $request->validated();

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        // #9799 — le PDF est produit AVANT de rendre le lien : sans cela le
        // vendeur partageait un document puis obtenait 404 au téléchargement,
        // et le lien envoyé au client était mort à la naissance. Endpoint
        // authentifié, donc pas de surface d'abus ; le service est idempotent
        // et n'échoue pas la requête si le rendu rate (le repli paresseux du
        // portail public retentera, et le 404 localisé reste la réponse).
        $this->pdfArchive->ensure($model);

        $share = $this->shareService->createShare(
            $model,
            (string) ($validated['email'] ?? ''),
            $expiresAt,
        );

        $this->auditLogger->recordSensitive($request, $actor, 'accounting.document_shared', $model);

        return response()->json([
            'data' => [
                'id' => $share->id,
                'document_id' => $share->document_id,
                'share_token' => $share->share_token,
                'shared_with_email' => $share->shared_with_email,
                'expires_at' => $share->expires_at?->toIso8601String(),
                'portal_url' => $this->shareService->portalUrl($share),
                'download_url' => $this->shareService->downloadUrl($share),
            ],
        ], 201);
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
        // #9763 — manager historique OU grant `accounting` (délégation #7761).
        return $this->accountingActor($request);
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
