<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\BillingCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * #7863 (Encaissements) — encaissements enregistrés au local (espèces / TPE
 * au comptoir), réservé au rôle `principal` (routes sous
 * `api.manager:principal`, même contrat que les profils de paiement #7727).
 *
 * Contrat de sécurité : isolation tenant par `BelongsToCompany` — un tenant
 * ne voit/crée que SES encaissements (scope fail-closed #3727), `company_id`
 * auto-rempli à la création, jamais accepté du client.
 */
class BillingCollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = BillingCollection::query()
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return new JsonResponse([
            'data' => [
                'items' => $collections->getCollection()
                    ->map(static fn (BillingCollection $collection): array => $collection->toApi())
                    ->values(),
                'meta' => [
                    'current_page' => $collections->currentPage(),
                    'last_page' => $collections->lastPage(),
                    'per_page' => $collections->perPage(),
                    'total' => $collections->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'method' => ['sometimes', Rule::in(BillingCollection::METHODS)],
            'note' => ['nullable', 'string', 'max:500'],
            'collected_at' => ['sometimes', 'date'],
        ]);

        $actorId = $request->user()?->getAuthIdentifier();

        $collection = BillingCollection::query()->create([
            'amount' => $validated['amount'],
            'currency' => strtoupper((string) $validated['currency']),
            'method' => $validated['method'] ?? 'cash',
            'note' => $validated['note'] ?? null,
            'collected_at' => isset($validated['collected_at'])
                ? Carbon::parse((string) $validated['collected_at'])
                : Carbon::now(),
            'created_by' => $actorId !== null ? (int) $actorId : null,
        ]);

        return new JsonResponse(['data' => $collection->toApi()], 201);
    }
}
