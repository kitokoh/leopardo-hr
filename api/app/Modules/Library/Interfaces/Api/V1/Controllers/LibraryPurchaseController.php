<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryPurchase;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibraryAccessPolicy;
use App\Modules\Library\Infrastructure\Services\LibraryFileService;
use App\Modules\Library\Infrastructure\Services\LibraryPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Achat à l'unité et vente en gros d'une ressource (BC-40 / BC-38, #9238).
 *
 * Routes tenant-scoped (auth Sanctum + middleware tenant) :
 *   GET  /library/purchases                          → mes achats
 *   POST /library/resources/{resource}/purchases     → initier un achat
 *   POST /library/purchases/{purchase}/confirm       → confirmer après paiement
 *   GET  /library/resources/{resource}/access        → mes droits réels
 *   GET  /library/resources/{resource}/download      → lien de téléchargement
 *
 * ⚠️ Aucun montant n'est lu depuis la requête : la quantité est la seule
 * donnée client, et le prix vient du catalogue serveur (`LibraryPricing`).
 * ⚠️ `confirm` ne fait pas confiance à l'appelant : le statut du paiement est
 * relu en base. Un appel sans paiement abouti rend 422, jamais un accès.
 * ⚠️ Isolation : tout est borné au tenant ET à l'employé de la session.
 */
class LibraryPurchaseController extends Controller
{
    public function __construct(
        private readonly LibraryPurchaseService $purchases,
        private readonly LibraryFileService $files,
    ) {}

    /**
     * GET /library/purchases — mes achats, les plus récents d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LibraryPurchase::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $rows = LibraryPurchase::query()
            ->where('employee_id', (int) $actor->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (LibraryPurchase $purchase): array => $this->payload($purchase))->all(),
        ]);
    }

    /**
     * POST /library/resources/{resource}/purchases — initier l'achat.
     *
     * Body : `quantity` (1..1000, défaut 1), `idempotency_key` (optionnel).
     */
    public function store(Request $request, int $resource): JsonResponse
    {
        $this->authorize('create', LibraryPurchase::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'idempotency_key' => ['sometimes', 'string', 'max:191'],
        ]);

        $row = $this->findVisibleResource($resource, (string) $actor->company_id);

        $purchase = $this->purchases->start(
            $row,
            $actor,
            (int) ($validated['quantity'] ?? 1),
            (string) ($validated['idempotency_key'] ?? ''),
        );

        return response()->json(['data' => $this->payload($purchase) + [
            'resource' => ['slug' => $row->slug, 'title' => $row->title],
        ]], 201);
    }

    /**
     * POST /library/purchases/{purchase}/confirm — confirmer la vente.
     *
     * Endpoint IDEMPOTENT : c'est le point de reprise après retour du
     * fournisseur de paiement (et le point de rejeu d'un webhook). Il ne
     * crédite rien lui-même — il constate le paiement côté serveur.
     */
    public function confirm(Request $request, int $purchase): JsonResponse
    {
        /** @var LibraryPurchase $row */
        $row = LibraryPurchase::query()->whereKey($purchase)->firstOrFail();

        $this->authorize('confirm', $row);

        return response()->json(['data' => $this->payload($this->purchases->confirm($row))]);
    }

    /**
     * GET /library/resources/{resource}/access — mes droits réels sur la
     * ressource (calculés serveur, jamais déduits du client).
     */
    public function access(Request $request, int $resource): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $row = $this->findVisibleResource($resource, (string) $actor->company_id);

        $policy = $this->policyFor($actor, $row);

        return response()->json([
            'data' => [
                'slug' => $row->slug,
                'access_model' => $row->access_model->value,
                'can_read' => $policy->canRead($row),
                'can_download' => $policy->canDownload($row),
                'requires_purchase' => $row->access_model->value === 'purchase' && ! $policy->canRead($row),
                'price_minor' => (int) $row->price_minor,
                'currency' => (string) $row->currency,
            ],
        ]);
    }

    /**
     * GET /library/resources/{resource}/download — lien de téléchargement.
     *
     * Même garde que la surface publique (le droit est vérifié ici), mais avec
     * le contexte d'achat de l'employé : c'est la voie qui rend un contenu
     * payant réellement consommable.
     */
    public function download(Request $request, int $resource): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $row = $this->findVisibleResource($resource, (string) $actor->company_id);

        $policy = $this->policyFor($actor, $row);

        if (! $policy->canDownload($row)) {
            return response()->json([
                'error' => 'download_forbidden',
                'reason' => $row->access_model->value,
            ], 403);
        }

        if ($row->file_key === null || ! $this->files->isConfigured()) {
            return response()->json(['error' => 'storage_not_configured'], 501);
        }

        $resolved = $this->files->resolve($row, (int) config('library.download_url_ttl', 300));

        return response()->json([
            'data' => [
                'url' => $resolved['url'],
                'format' => $resolved['format'],
                'size' => $resolved['size'],
            ],
        ]);
    }

    /**
     * Contexte d'accès de la session : c'est ici, et nulle part ailleurs, que
     * le drapeau `purchased` est établi.
     */
    private function policyFor(Employee $actor, LibraryResource $resource): LibraryAccessPolicy
    {
        return new LibraryAccessPolicy(
            viewerTenantId: (string) $actor->company_id,
            purchased: $this->purchases->hasPaidAccess(
                (string) $actor->company_id,
                (int) $actor->id,
                (int) $resource->id,
            ),
            subscribed: false,
        );
    }

    /**
     * Résolution dans le périmètre VISIBLE par le tenant : catalogue public
     * publié (`company_id` NULL) ou ressource propre au tenant. Une ressource
     * d'un autre tenant est un 404, jamais une fuite d'existence.
     */
    private function findVisibleResource(int $id, string $companyId): LibraryResource
    {
        /** @var LibraryResource $resource */
        $resource = LibraryResource::query()
            // Le catalogue public Leopardo vit en `company_id` NULL : le scope
            // tenant du modèle l'exclurait. Lecture cross-tenant ASSUMÉE et
            // bornée ici (catalogue publié + ressources du tenant courant),
            // avec sa justification versionnée (#7960).
            ->crossTenantForSystemTask('library purchase: catalogue public Leopardo (company_id NULL) + ressources du tenant courant')
            ->whereKey($id)
            ->where(function ($query) use ($companyId): void {
                $query->where(function ($public): void {
                    $public->whereNull('company_id')
                        ->where('status', LibraryResourceStatus::Published->value);
                })->orWhere('company_id', $companyId);
            })
            ->firstOrFail();

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LibraryPurchase $purchase): array
    {
        return [
            'id' => (int) $purchase->id,
            'status' => $purchase->status->value,
            'quantity' => (int) $purchase->quantity,
            'unit_price_minor' => (int) $purchase->unit_price_minor,
            'total_minor' => (int) $purchase->total_minor,
            'net_minor' => (int) $purchase->net_minor,
            'vat_minor' => (int) $purchase->vat_minor,
            'vat_rate' => (float) $purchase->vat_rate,
            'currency' => (string) $purchase->currency,
            'invoice_number' => $purchase->invoice_number,
            'paid_at' => $purchase->paid_at?->toIso8601String(),
            'refunded_at' => $purchase->refunded_at?->toIso8601String(),
            'resource_id' => (int) $purchase->resource_id,
        ];
    }
}
