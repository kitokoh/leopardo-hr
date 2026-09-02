<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelAccountVisit;
use App\Modules\FuelStation\Domain\Models\FuelProfessionalAccount;
use App\Modules\FuelStation\Infrastructure\Services\FuelCrmService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelAccountRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelAccountVisitRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelAccountConsentsRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Intégration CRM FuelStation (FUEL-016, issue #5810).
 *
 * Comptes professionnels B2B, visites commerciales et consentements
 * marketing — tenant-scoped, RBAC manager deny-by-default, événements
 * versionnés publiés dans l'outbox (FUEL-015). Aucune lecture des leads du
 * CRM commercial Leopardo (isolation dual-context).
 */
class FuelCrmController extends Controller
{
    public function __construct(private readonly FuelCrmService $crm) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAnyAccount', FuelProfessionalAccount::class);

        $query = FuelProfessionalAccount::query()
            ->withCount('visits')
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

        $accounts = $query
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($accounts->items())->map(fn (FuelProfessionalAccount $account): array => $this->accountPayload($account)),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
                'per_page' => $accounts->perPage(),
            ],
        ]);
    }

    public function store(StoreFuelAccountRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('createAccount', FuelProfessionalAccount::class);

        $account = $this->crm->upsertAccount($actor, $request->validated());

        return response()->json(['data' => $this->accountPayload($account)], 201);
    }

    public function show(Request $request, FuelProfessionalAccount $account): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($account, $actor);
        $this->authorize('viewAccount', $account);

        $account->loadCount('visits');

        return response()->json(['data' => $this->accountPayload($account)]);
    }

    public function visits(Request $request, FuelProfessionalAccount $account): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($account, $actor);
        $this->authorize('viewVisits', $account);

        $visits = $account->visits()
            ->orderByDesc('visited_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($visits->items())->map(fn ($visit): array => $this->visitPayload($visit)),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
                'per_page' => $visits->perPage(),
            ],
        ]);
    }

    public function recordVisit(StoreFuelAccountVisitRequest $request, FuelProfessionalAccount $account): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($account, $actor);
        $this->authorize('recordVisit', $account);

        $visit = $this->crm->recordVisit($account, $actor, $request->validated());

        return response()->json(['data' => $this->visitPayload($visit)], 201);
    }

    public function updateConsents(UpdateFuelAccountConsentsRequest $request, FuelProfessionalAccount $account): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($account, $actor);
        $this->authorize('updateConsents', $account);

        $account = $this->crm->updateConsents($account, $actor, $request->validated());

        return response()->json(['data' => $this->accountPayload($account)]);
    }

    /** @return array<string, mixed> */
    private function accountPayload(FuelProfessionalAccount $account): array
    {
        return [
            'id' => $account->id,
            'station_id' => $account->station_id,
            'code' => $account->code,
            'name' => $account->name,
            'industry' => $account->industry,
            'status' => $account->status,
            'consents' => $account->consentSummary(),
            'visits_count' => $account->visits_count ?? null,
            'external_id' => $account->external_id,
            'created_by' => $account->created_by,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function visitPayload(FuelAccountVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'account_id' => $visit->account_id,
            'visited_at' => $visit->visited_at->toIso8601String(),
            'purpose' => $visit->purpose,
            'notes_redacted' => $visit->notes_redacted,
            'external_id' => $visit->external_id,
            'created_by' => $visit->created_by,
            'created_at' => $visit->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantOwned(Model $model, Employee $actor): void
    {
        if ($model->getAttribute('company_id') !== $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
