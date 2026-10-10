<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Exceptions\DomainException;
use App\Modules\Vtc\Domain\Enums\VtcVehicleCategory;
use App\Modules\Vtc\Domain\Enums\VtcVehicleStatus;
use App\Modules\Vtc\Domain\Models\VtcVehicle;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\VehicleRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcVehicleResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD des véhicules (BC-34 VTC, VTC-06/#8362, rôle vtc.admin).
 *
 * Plaque unique par tenant : un doublon est renvoyé en 422 propre (jamais
 * une 500). La suppression d'un véhicule encore affecté à un chauffeur est
 * refusée (409 VTC_VEHICLE_ASSIGNED) — l'affectation doit être retirée
 * d'abord.
 */
final class VtcVehicleAdminController
{
    public function index(): AnonymousResourceCollection
    {
        return VtcVehicleResource::collection(
            VtcVehicle::query()->orderBy('plate')->get()
        );
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            /** @var VtcVehicle $vehicle */
            $vehicle = VtcVehicle::query()->create($this->attributes($validated));
        } catch (QueryException) {
            throw $this->duplicatePlate((string) $validated['plate']);
        }

        return (new VtcVehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function update(VehicleRequest $request, int $id): VtcVehicleResource
    {
        $vehicle = $this->findVehicle($id);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $vehicle->forceFill($this->attributes($validated))->save();
        } catch (QueryException) {
            throw $this->duplicatePlate((string) $validated['plate']);
        }

        return new VtcVehicleResource($vehicle->refresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $vehicle = $this->findVehicle($id);

        if ($vehicle->drivers()->exists()) {
            throw new DomainException(
                (string) __('vtc.vehicle_delete_assigned'),
                409,
                'VTC_VEHICLE_ASSIGNED'
            );
        }

        $vehicle->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'plate' => strtoupper(trim((string) $validated['plate'])),
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'color' => $validated['color'] ?? null,
            'seats' => $validated['seats'] ?? 4,
            'category' => $validated['category'] ?? VtcVehicleCategory::Berline->value,
            'status' => $validated['status'] ?? VtcVehicleStatus::Active->value,
        ];
    }

    private function duplicatePlate(string $plate): DomainException
    {
        return new DomainException(
            (string) __('vtc.vehicle_plate_taken', ['plate' => $plate]),
            422,
            'VTC_VEHICLE_PLATE_TAKEN'
        );
    }

    private function findVehicle(int $id): VtcVehicle
    {
        /** @var VtcVehicle|null $vehicle */
        $vehicle = VtcVehicle::query()->whereKey($id)->first();

        if (! $vehicle instanceof VtcVehicle) {
            abort(404);
        }

        return $vehicle;
    }
}
