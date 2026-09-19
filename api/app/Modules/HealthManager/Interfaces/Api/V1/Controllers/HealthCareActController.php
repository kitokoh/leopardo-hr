<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthCareActRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthCareActRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue des actes de soins facturables — HC-007 (#7791, BC-30).
 *
 * RBAC (HealthCareActPolicy) : direction + facturation UNIQUEMENT.
 * Suppression refusée (422 HEALTH_RESOURCE_IN_USE) dès qu'une ligne de
 * facture référence l'acte : les prix sont figés à la facturation mais
 * l'acte d'origine doit rester consultable (traçabilité, spec §4).
 */
class HealthCareActController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthCareAct::class);

        $query = HealthCareAct::query()->where('company_id', $actor->company_id);

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $acts = $query->orderBy('code')->paginate((int) ($request->input('per_page') ?? 50));

        return response()->json([
            'data' => collect($acts->items())->map(fn (HealthCareAct $act): array => $this->payload($act)),
            'meta' => [
                'current_page' => $acts->currentPage(),
                'per_page' => $acts->perPage(),
                'total' => $acts->total(),
            ],
        ]);
    }

    public function store(StoreHealthCareActRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthCareAct::class);

        /** @var HealthCareAct $act */
        $act = HealthCareAct::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'currency' => strtoupper((string) ($request->validated('currency') ?? currentCompany()->currency)),
            'active' => (bool) ($request->validated('active') ?? true),
        ]));

        return response()->json(['data' => $this->payload($act)], 201);
    }

    public function update(UpdateHealthCareActRequest $request, HealthCareAct $careAct): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($careAct, $actor->company_id);
        $this->authorize('update', $careAct);

        // Modifier le prix du catalogue ne réécrit JAMAIS les lignes de
        // factures existantes (prix figés à la facturation, spec §4).
        $careAct->update($request->validated());

        return response()->json(['data' => $this->payload($careAct->refresh())]);
    }

    public function destroy(Request $request, HealthCareAct $careAct): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($careAct, $actor->company_id);
        $this->authorize('delete', $careAct);

        if ($careAct->invoiceItems()->exists()) {
            throw new HealthResourceInUseException;
        }

        $careAct->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthCareAct $act): array
    {
        return [
            'id' => (int) $act->getAttribute('id'),
            'code' => $act->code,
            'label' => $act->label,
            'category' => $act->category,
            'price' => $act->price,
            'currency' => $act->currency,
            'active' => $act->active,
        ];
    }
}
