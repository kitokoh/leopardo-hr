<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\RecordVehiclePositionAction;
use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelVehiclePositionLog;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RecordTravelVehiclePositionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelVehicleRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelVehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-306 (#6036) — CRUD de la flotte propre de l'agence.
 *
 * Même schéma que `TravelCarrierController` : 404 sûr cross-tenant, jamais
 * 403 sur la ressource elle-même.
 */
class TravelVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelVehicle::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $vehicles = TravelVehicle::query()
            ->orderBy('code')
            ->paginate($perPage);

        return TravelVehicleResource::collection($vehicles)->response();
    }

    public function store(StoreTravelVehicleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelVehicle::class)) {
            abort(403);
        }

        $vehicle = TravelVehicle::query()->create($request->validated());

        return (new TravelVehicleResource($vehicle->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        return (new TravelVehicleResource($travelVehicle))->response();
    }

    public function update(UpdateTravelVehicleRequest $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelVehicle)) {
            abort(403);
        }

        $travelVehicle->update($request->validated());

        return (new TravelVehicleResource($travelVehicle))->response();
    }

    public function destroy(Request $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelVehicle)) {
            abort(403);
        }

        $travelVehicle->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * #8540 — ingestion de la dernière position connue d'un véhicule.
     *
     * Réservé à `travel.manage` (dispositif autorisé) ; cross-tenant → 404.
     * Aucune position n'est déduite ni inventée : le corps porte la source.
     */
    public function recordPosition(
        RecordTravelVehiclePositionRequest $request,
        TravelVehicle $travelVehicle,
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelVehicle)) {
            abort(403);
        }

        $vehicle = app(RecordVehiclePositionAction::class)->execute(
            $travelVehicle,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            TravelVehiclePositionSource::from((string) $request->validated('source')),
            // #8548 — horodatage de la MESURE transmis par le dispositif ;
            // absent, l'instant de réception fait foi (jamais une date devinée).
            $this->recordedAt($request),
        );

        return (new TravelVehicleResource($vehicle))->response();
    }

    /**
     * #8548 — HISTORIQUE des positions réellement transmises par le véhicule.
     *
     * Lecture bornée : tenant (scope `BelongsToCompany`), véhicule, et fenêtre
     * temporelle optionnelle. Un véhicule sans dispositif retourne une liste
     * VIDE — jamais des points fabriqués. Cross-tenant → 404.
     */
    public function positionHistory(Request $request, TravelVehicle $travelVehicle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelVehicle->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelVehicle)) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);

        $query = TravelVehiclePositionLog::query()
            ->where('vehicle_id', $travelVehicle->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if (isset($validated['from'])) {
            $query->where('recorded_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('recorded_at', '<=', $validated['to']);
        }

        $logs = $query->limit($limit)->get();

        return new JsonResponse([
            'data' => [
                'vehicle_id' => $travelVehicle->id,
                'vehicle_code' => $travelVehicle->code,
                'count' => $logs->count(),
                'points' => $logs->map(fn (TravelVehiclePositionLog $log): array => [
                    'latitude' => $log->latitude,
                    'longitude' => $log->longitude,
                    'source' => $log->source->value,
                    'source_label' => $log->source->label(),
                    // Une estimation n'est jamais présentée comme une mesure.
                    'is_measured' => $log->source->isMeasured(),
                    'recorded_at' => $log->recorded_at->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Horodatage de mesure fourni par le dispositif, ou `null` (réception).
     */
    private function recordedAt(RecordTravelVehiclePositionRequest $request): ?Carbon
    {
        $value = $request->validated('recorded_at');

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
