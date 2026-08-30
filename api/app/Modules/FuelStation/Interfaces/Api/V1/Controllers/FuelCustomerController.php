<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Domain\Models\FuelCustomerVisit;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelCrmContractService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelCustomerRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelCustomerVisitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API clients FuelStation (FUEL-016, #5810).
 *
 * CRM tenant uniquement (jamais les leads Leopardo) : CRUD manager,
 * consentement marketing explicite horodaté, visites avec crédit de
 * fidélité idempotent. 404 sûr cross-tenant avant Policy.
 */
class FuelCustomerController extends Controller
{
    public function __construct(private readonly FuelCrmContractService $crm) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelCustomer::class);

        $query = FuelCustomer::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', (int) $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $customers = $query->orderBy('name')->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $customers->map(fn (FuelCustomer $customer): array => $this->payload($customer)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(StoreFuelCustomerRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelCustomer::class);

        $customer = $this->crm->registerCustomer($actor, $station, $request->validated());

        return response()->json(['data' => $this->payload($customer)], 201);
    }

    public function show(Request $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $customer);

        return response()->json(['data' => $this->payload($customer)]);
    }

    public function update(StoreFuelCustomerRequest $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return response()->json(['data' => $this->payload($customer->refresh())]);
    }

    public function consent(Request $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        $consent = (bool) $request->boolean('marketing_consent');

        return response()->json(['data' => $this->payload($this->crm->updateConsent($actor, $customer, $consent))]);
    }

    public function visits(Request $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $customer);

        $visits = FuelCustomerVisit::query()
            ->where('company_id', $actor->company_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('visited_at')
            ->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $visits->map(fn (FuelCustomerVisit $visit): array => $this->visitPayload($visit)),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    public function storeVisit(StoreFuelCustomerVisitRequest $request, FuelCustomer $customer, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== $actor->company_id || $station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        $visit = $this->crm->recordVisit($actor, $customer, $station, $request->validated());

        return response()->json(['data' => $this->visitPayload($visit)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'company_id' => $customer->company_id,
            'station_id' => $customer->station_id,
            'name' => $customer->name,
            'contact_email' => $customer->contact_email,
            'phone' => $customer->phone,
            'marketing_consent' => $customer->marketing_consent,
            'opted_in_at' => $customer->opted_in_at?->toISOString(),
            'opted_out_at' => $customer->opted_out_at?->toISOString(),
            'loyalty_points' => $customer->loyalty_points,
            'status' => $customer->status,
            'external_id' => $customer->external_id,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visitPayload(FuelCustomerVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'company_id' => $visit->company_id,
            'customer_id' => $visit->customer_id,
            'station_id' => $visit->station_id,
            'visited_at' => $visit->visited_at?->toISOString(),
            'notes' => $visit->notes,
            'idempotency_key' => $visit->idempotency_key,
        ];
    }
}
