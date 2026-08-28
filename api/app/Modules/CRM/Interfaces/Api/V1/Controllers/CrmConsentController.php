<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\CommunicationConsentService;
use App\Modules\CRM\Domain\Enums\ConsentChannel;
use App\Modules\CRM\Domain\Enums\ConsentPurpose;
use App\Modules\CRM\Domain\Enums\ConsentSource;
use App\Modules\CRM\Domain\Enums\ConsentStatus;
use App\Modules\CRM\Domain\Models\CrmConsent;
use App\Modules\CRM\Interfaces\Api\V1\Requests\GrantConsentRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\RevokeConsentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consentements et préférences de communication CRM — Issue #5722.
 *
 * RBAC : lecture = tout manager du tenant (`api.manager`) ; écritures =
 * `principal` / `marketing` (middleware + Policy `CrmConsentPolicy`,
 * jamais de garde inline).
 *
 * Isolation tenant : `CrmConsent` porte le trait `BelongsToCompany` (scope
 * global + auto-remplissage company_id, fail-closed #3727) — un consentement
 * d'un autre tenant est introuvable (404), jamais visible.
 */
class CrmConsentController extends Controller
{
    public function __construct(private readonly CommunicationConsentService $consents) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmConsent::class);

        $request->validate([
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', ConsentChannel::values())],
            'status' => ['nullable', 'string', 'in:'.implode(',', ConsentStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $request->integer('per_page', 20);
        $contactId = $request->integer('contact_id');
        $channel = $request->string('channel')->toString();
        $status = $request->string('status')->toString();

        $query = CrmConsent::query()
            ->orderByDesc('id');

        if ($contactId > 0) {
            $query->where('contact_id', $contactId);
        }

        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $consent) {
            $items[] = $this->serialize($consent);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(GrantConsentRequest $request): JsonResponse
    {
        $this->authorize('create', CrmConsent::class);

        $contactId = $request->integer('contact_id');
        $channel = ConsentChannel::from($request->string('channel')->toString());
        $purpose = ConsentPurpose::from($request->string('purpose')->toString());
        $source = ConsentSource::from($request->string('source')->toString());
        $sourceRef = $request->filled('source_ref') ? $request->string('source_ref')->toString() : null;
        $metadataInput = $request->input('metadata');
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($metadataInput) ? $metadataInput : [];

        $consent = $request->string('action')->toString() === 'granted'
            ? $this->consents->grant($contactId, $channel, $purpose, $source, $sourceRef, $metadata)
            : $this->consents->deny($contactId, $channel, $purpose, $source, $sourceRef, $metadata);

        return response()->json(['data' => $this->serialize($consent)], 201);
    }

    public function show(CrmConsent $consent): JsonResponse
    {
        $this->assertTenantScope(request(), $consent);
        $this->authorize('view', $consent);

        return response()->json(['data' => $this->serialize($consent)]);
    }

    public function revoke(CrmConsent $consent, RevokeConsentRequest $request): JsonResponse
    {
        $this->assertTenantScope($request, $consent);
        $this->authorize('revoke', $consent);

        $source = ConsentSource::from($request->string('source')->toString());
        $sourceRef = $request->filled('source_ref') ? $request->string('source_ref')->toString() : null;

        $updated = $this->consents->withdraw(
            $consent->contact_id,
            ConsentChannel::from($consent->channel),
            ConsentPurpose::from($consent->purpose),
            $source,
            $sourceRef,
        );

        return response()->json(['data' => $this->serialize($updated)]);
    }

    private function assertTenantScope(Request $request, CrmConsent $consent): void
    {
        // Le binding implicite est résolu AVANT le middleware tenant
        // (SubstituteBindings global) : le scope BelongsToCompany n'est pas
        // encore appliqué. Garde explicite — un consentement d'un autre
        // tenant est introuvable (404), jamais visible (même pattern que
        // AccountingContactController::assertTenantScope).
        $companyId = $request->user()?->getAttribute('company_id');

        if (is_string($companyId) && (string) $consent->company_id !== $companyId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CrmConsent $consent): array
    {
        return [
            'id' => $consent->id,
            'contact_id' => $consent->contact_id,
            'channel' => $consent->channel,
            'purpose' => $consent->purpose,
            'status' => $consent->status,
            'source' => $consent->source,
            'source_ref' => $consent->source_ref,
            'granted_at' => $consent->granted_at?->toIso8601String(),
            'revoked_at' => $consent->revoked_at?->toIso8601String(),
            'metadata' => $consent->metadata,
        ];
    }
}
