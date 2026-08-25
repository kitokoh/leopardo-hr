<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5522 — Consultation de l'audit des partages du portail client (RGPD loi 18-07).
 *
 * Réponse à un incident RGPD : « qui a consulté / téléchargé quel document
 * partagé, quand, depuis quelle IP » — pour un document donné, la liste
 * paginée des accès `accounting.share.info` / `accounting.share.download`
 * tracés par `PublicDocumentShareController` (#5429, unifiés #5520).
 *
 * RBAC : managers principal/comptable (middleware api.manager) ; isolation
 * tenant fail-closed via le scope global BelongsToCompany (document inconnu
 * ou cross-tenant → 404).
 */
final class ShareAccessController extends Controller
{
    /**
     * GET /api/v1/accounting/documents/shared/{document}/accesses
     *
     * Liste paginée (date décroissante) des consultations/téléchargements du
     * portail pour TOUS les partages du document. Chaque entrée expose
     * action (share.info|share.download), ip_address, user_agent,
     * request_id de corrélation et created_at — sans jamais exposer le
     * share_token lui-même (le token reste la credential du portail).
     */
    public function index(Request $request, int $document): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Isolation tenant fail-closed : scope global BelongsToCompany actif
        // (middleware tenant) → document inconnu ou cross-tenant = 404.
        $doc = AccountingDocument::query()->findOrFail($document);

        $shareIds = AccountingDocumentShare::query()
            ->where('document_id', $doc->id)
            ->pluck('id');

        $query = AuditLog::query()
            ->forCompany((string) $actor->company_id)
            ->where('auditable_type', (new AccountingDocumentShare())->getMorphClass())
            ->whereIn('auditable_id', $shareIds)
            ->whereIn('action', ['accounting.share.info', 'accounting.share.download']);

        return AuditLogResource::collection(
            $query->orderByDesc('created_at')->paginate((int) ($validated['per_page'] ?? 25))
        )->response();
    }
}
