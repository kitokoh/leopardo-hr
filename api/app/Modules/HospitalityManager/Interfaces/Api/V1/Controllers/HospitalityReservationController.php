<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityReservationRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\UpdateHospitalityReservationRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des réservations au guichet — HOSP-004 (#7946).
 *
 * CRUD + transitions gardées (409 INVALID_RESERVATION_TRANSITION) ; la
 * création et l'édition sont anti-overbooking transactionnelles
 * (409 HOSPITALITY_NO_AVAILABILITY). Tenant TOUJOURS re-vérifié (404).
 */
class HospitalityReservationController extends Controller
{
    use ChecksHospitalitySolution;

    public function __construct(
        private readonly HospitalityReservationService $reservations
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HospitalityReservation::class);

        $query = HospitalityReservation::query()->where('company_id', $actor->company_id);

        if ($request->filled('property_id')) {
            $query->where('property_id', (int) $request->input('property_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->where('check_out', '>', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('check_in', '<', $request->input('to'));
        }

        $reservations = $query->orderByDesc('check_in')->orderByDesc('id')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($reservations->items())->map(fn (HospitalityReservation $reservation): array => $this->payload($reservation)),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    public function store(StoreHospitalityReservationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', [HospitalityReservation::class, (int) $request->input('property_id')]);

        $reservation = $this->reservations->createDeskReservation($actor->company_id, $request->validated());

        // Rejeu idempotent (clé déjà connue) → 200 avec l'existant, pas 201.
        $status = $reservation->wasRecentlyCreated ? 201 : 200;

        return response()->json(['data' => $this->payload($reservation)], $status);
    }

    public function show(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($reservation, $actor->company_id);
        $this->authorize('view', $reservation);

        return response()->json(['data' => $this->payload($reservation)]);
    }

    public function update(UpdateHospitalityReservationRequest $request, HospitalityReservation $reservation): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($reservation, $actor->company_id);
        $this->authorize('update', $reservation);

        $reservation = $this->reservations->updateReservation($reservation, $request->validated());

        return response()->json(['data' => $this->payload($reservation)]);
    }

    public function confirm(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        return $this->transitionTo($request, $reservation, HospitalityReservation::STATUS_CONFIRMED);
    }

    public function checkIn(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        return $this->transitionTo($request, $reservation, HospitalityReservation::STATUS_CHECKED_IN);
    }

    public function checkOut(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        return $this->transitionTo($request, $reservation, HospitalityReservation::STATUS_CHECKED_OUT);
    }

    public function cancel(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        return $this->transitionTo($request, $reservation, HospitalityReservation::STATUS_CANCELLED);
    }

    public function noShow(Request $request, HospitalityReservation $reservation): JsonResponse
    {
        return $this->transitionTo($request, $reservation, HospitalityReservation::STATUS_NO_SHOW);
    }

    private function transitionTo(Request $request, HospitalityReservation $reservation, string $target): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($reservation, $actor->company_id);
        $this->authorize('transition', $reservation);

        $reservation = $this->reservations->transition($reservation, $target);

        return response()->json(['data' => $this->payload($reservation)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityReservation $reservation): array
    {
        return [
            'id' => (int) $reservation->getAttribute('id'),
            'reference' => $reservation->reference,
            'property_id' => (int) $reservation->getAttribute('property_id'),
            'room_type_id' => (int) $reservation->getAttribute('room_type_id'),
            'unit_id' => $reservation->unit_id,
            'guest_name' => $reservation->guest_name,
            'contact_email' => $reservation->contact_email,
            'contact_phone' => $reservation->contact_phone,
            'check_in' => $reservation->check_in?->toDateString(),
            'check_out' => $reservation->check_out?->toDateString(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'status' => $reservation->status,
            'total_amount_minor' => $reservation->total_amount_minor,
            'currency' => $reservation->currency,
            'source' => $reservation->source,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'notes' => $reservation->notes,
            'version' => $reservation->version,
            'allowed_transitions' => HospitalityReservation::TRANSITIONS[$reservation->status] ?? [],
        ];
    }
}
