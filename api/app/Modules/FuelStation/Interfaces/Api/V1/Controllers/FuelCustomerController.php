<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Infrastructure\Services\FuelLoyaltyService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SetFuelConsentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelCustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clients & fidélité (FUEL-016, issue #5810).
 *
 * deny-by-default (FuelCustomerPolicy) : CRUD manager. Upsert idempotent
 * par external_id ; consentement marketing explicite (RGPD) ; dépense de
 * points bornée au solde. Aucune lecture du CRM commercial Leopardo.
 */
class FuelCustomerController extends Controller
{
    public function __construct(private readonly FuelLoyaltyService $loyalty) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelCustomer::class);

        $query = FuelCustomer::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('marketing_consent')) {
            $query->where('marketing_consent', $request->boolean('marketing_consent'));
        }

        $search = $request->input('q');
        if (is_string($search) && $search !== '') {
            $query->where('full_name', 'ilike', '%'.$search.'%');
        }

        $customers = $query->orderBy('full_name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($customers->items())->map(fn (FuelCustomer $c): array => $this->payload($c)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(StoreFuelCustomerRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelCustomer::class);

        $customer = $this->loyalty->upsert($actor, $request->validated());

        return response()->json(['data' => $this->payload($customer)], 200);
    }

    public function show(Request $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $customer);

        return response()->json(['data' => $this->payload($customer)]);
    }

    public function consent(SetFuelConsentRequest $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        $customer = $this->loyalty->setConsent($actor, $customer, $request->boolean('marketing_consent'));

        return response()->json(['data' => $this->payload($customer)]);
    }

    public function redeem(Request $request, FuelCustomer $customer): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($customer->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $customer);

        $points = (int) $request->input('points', 0);
        $reason = (string) $request->input('reason', '');

        abort_if(trim($reason) === '', 422, 'REDEEM_REASON_REQUIRED');

        $customer = $this->loyalty->redeemPoints($actor, $customer, $points, $reason);

        return response()->json(['data' => $this->payload($customer)]);
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
            'external_id' => $customer->external_id,
            'full_name' => $customer->full_name,
            'marketing_consent' => $customer->marketing_consent,
            'loyalty_points' => $customer->loyalty_points,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
