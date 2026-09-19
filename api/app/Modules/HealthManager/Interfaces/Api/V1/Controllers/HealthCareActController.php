<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthCareAct;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthCareActRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthCareActRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API du catalogue d'actes médicaux — HC-007 (#7791, BC-30).
 *
 * Catalogue tarifaire du tenant (code, libellé, catégorie, prix, actif),
 * géré par `health.billing` et `health.admin` uniquement. Pas de DELETE :
 * un acte déjà facturé est référencé par des lignes de facture (FK
 * restrictive) — il se DÉSACTIVE (`is_active=false`) et disparaît des
 * saisies, sans jamais altérer les factures existantes (prix figés).
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

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        // Recherche : code ou libellé (saisie rapide côté facturation).
        $term = trim((string) $request->input('q', ''));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->where('name', 'ilike', $like)
                    ->orWhere('code', 'ilike', $like);
            });
        }

        $acts = $query->orderBy('code')
            ->paginate((int) ($request->input('per_page') ?? 15));

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

        $this->authorize('create', HealthCareAct::class);

        /** @var HealthCareAct $act */
        $act = HealthCareAct::query()->create($request->validated());

        return response()->json(['data' => $this->payload($act)], 201);
    }

    public function show(Request $request, HealthCareAct $careAct): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($careAct, $actor->company_id);
        $this->authorize('view', $careAct);

        return response()->json(['data' => $this->payload($careAct)]);
    }

    /**
     * Changer le prix du catalogue ne modifie JAMAIS une facture émise :
     * les lignes de facture portent leur propre prix FIGÉ (critère HC-007).
     */
    public function update(UpdateHealthCareActRequest $request, HealthCareAct $careAct): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($careAct, $actor->company_id);
        $this->authorize('update', $careAct);

        $careAct->update($request->validated());

        return response()->json(['data' => $this->payload($careAct->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthCareAct $act): array
    {
        return [
            'id' => (int) $act->getAttribute('id'),
            'code' => $act->code,
            'name' => $act->name,
            'category' => $act->category,
            'price' => $act->price,
            'is_active' => $act->is_active,
        ];
    }
}
