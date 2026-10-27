<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRentPayment;
use App\Modules\HospitalityManager\Domain\Policies\Concerns\ChecksHospitalityPropertyAccess;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityRentService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\RecordHospitalityRentPaymentRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\BoundsPagination;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des échéances de loyer — HOSP-005 (#7947, BC-32).
 *
 * `GET /hospitality/rent-payments` (filtres lease/status/period) et
 * `POST /hospitality/rent-payments/{payment}/record` (encaissement partiel
 * ou total).
 *
 * Le statut `late` est DÉRIVÉ : il est recalculé à la lecture
 * (`refreshLateStatus`), pas seulement par la commande planifiée. Sans cela,
 * une échéance dont la date vient de passer s'afficherait encore `due` alors
 * que le calcul est parfaitement déterministe — l'écran de relance montrerait
 * alors des impayés comme « à échoir ».
 */
class HospitalityRentPaymentController extends Controller
{
    use BoundsPagination;
    use ChecksHospitalityPropertyAccess;
    use ChecksHospitalitySolution;

    public function __construct(private readonly HospitalityRentService $rents) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', HospitalityRentPayment::class);

        $query = HospitalityRentPayment::query()
            ->where('company_id', $actor->company_id);

        // Un bail peut appartenir à un établissement auquel l'acteur n'a pas
        // accès : la lecture est donc bornée aux établissements visibles.
        $propertyIds = $this->visiblePropertyIds($actor);
        if ($propertyIds !== null) {
            $query->whereIn('property_id', $propertyIds);
        }

        if ($request->filled('lease_id')) {
            $query->where('lease_id', $request->integer('lease_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('period')) {
            $query->where('period', $request->string('period')->toString());
        }

        $payments = $query->orderByDesc('due_on')->paginate($this->boundedPerPage($request, 15));

        return response()->json([
            'data' => collect($payments->items())->map(
                fn (HospitalityRentPayment $payment): array => $this->payload($this->rents->refreshLateStatus($payment))
            ),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function record(RecordHospitalityRentPaymentRequest $request, HospitalityRentPayment $payment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($payment, $actor->company_id);
        $this->authorize('record', $payment);

        $payment = $this->rents->recordPayment(
            $payment,
            $request->amountMinor(),
            $request->paidAt(),
            $request->paymentMethod(),
            $request->reference(),
            $request->notes(),
        );

        return response()->json(['data' => $this->payload($payment)]);
    }

    /**
     * Établissements visibles par l'acteur, ou `null` si l'accès est global.
     *
     * En l'absence d'assignation `hospitality_property` dans le tenant, le
     * filtre `company_id` suffit (`null`). Dès que le scoping est actif,
     * `accessibleResourceIds` renvoie la liste exacte (`null` = tout).
     *
     * @return list<int>|null
     */
    private function visiblePropertyIds(Employee $actor): ?array
    {
        if (! $actor->isResourceTypeScoped('hospitality_property')) {
            return null;
        }

        $accessible = $actor->accessibleResourceIds(
            'hospitality_property',
            EmployeeResourceAssignment::LEVEL_VIEW
        );

        if ($accessible === null) {
            return null;
        }

        return array_map(static fn (mixed $id): int => (int) $id, $accessible);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityRentPayment $payment): array
    {
        // `getAttribute()` rend `mixed` : la conversion est explicite.
        $id = $payment->getKey();
        $leaseId = $payment->getAttribute('lease_id');
        $propertyId = $payment->getAttribute('property_id');
        $unitId = $payment->getAttribute('unit_id');

        return [
            'id' => is_numeric($id) ? (int) $id : 0,
            'lease_id' => is_numeric($leaseId) ? (int) $leaseId : 0,
            'property_id' => is_numeric($propertyId) ? (int) $propertyId : 0,
            'unit_id' => is_numeric($unitId) ? (int) $unitId : 0,
            'period' => $payment->period,
            'due_on' => $payment->due_on->toDateString(),
            'amount_minor' => $payment->amount_minor,
            'paid_amount_minor' => $payment->paid_amount_minor,
            'outstanding_minor' => $payment->outstandingMinor(),
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
        ];
    }
}
