<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Exceptions\DomainException;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcVehicle;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\DriverRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcDriverResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * CRUD des chauffeurs (BC-34 VTC, VTC-06/#8362, rôle vtc.admin).
 *
 * `user_id` rattache la fiche à un compte employé DU TENANT (surface API
 * chauffeur VTC-05) — l'existence du compte et du véhicule dans le tenant
 * est vérifiée (422 propre). La suppression d'un chauffeur ayant des
 * courses est refusée (409 VTC_DRIVER_HAS_RIDES — préférer la suspension,
 * l'historique des courses est conservé).
 */
final class VtcDriverAdminController
{
    public function index(): AnonymousResourceCollection
    {
        return VtcDriverResource::collection(
            VtcDriver::query()->orderBy('name')->get()
        );
    }

    public function store(DriverRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $this->assertReferencesExist($validated);

        /** @var VtcDriver $driver */
        $driver = VtcDriver::query()->create([
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? VtcDriverStatus::Offline->value,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
        ]);

        return (new VtcDriverResource($driver))->response()->setStatusCode(201);
    }

    public function update(DriverRequest $request, int $id): VtcDriverResource
    {
        $driver = $this->findDriver($id);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $this->assertReferencesExist($validated);

        $driver->forceFill([
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? $driver->status->value,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
        ])->save();

        return new VtcDriverResource($driver->refresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $driver = $this->findDriver($id);

        if ($driver->rides()->exists()) {
            throw new DomainException(
                (string) __('vtc.driver_delete_has_rides'),
                409,
                'VTC_DRIVER_HAS_RIDES'
            );
        }

        $driver->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Le compte rattaché et le véhicule doivent exister DANS LE TENANT
     * (scopes BelongsToCompany) — 422 propre sinon.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function assertReferencesExist(array $validated): void
    {
        if (isset($validated['user_id'])
            && ! Employee::query()->whereKey($validated['user_id'])->exists()) {
            throw ValidationException::withMessages([
                'user_id' => [(string) __('vtc.driver_user_not_found')],
            ]);
        }

        if (isset($validated['vehicle_id'])
            && ! VtcVehicle::query()->whereKey($validated['vehicle_id'])->exists()) {
            throw ValidationException::withMessages([
                'vehicle_id' => [(string) __('vtc.driver_vehicle_not_found')],
            ]);
        }
    }

    private function findDriver(int $id): VtcDriver
    {
        /** @var VtcDriver|null $driver */
        $driver = VtcDriver::query()->whereKey($id)->first();

        if (! $driver instanceof VtcDriver) {
            abort(404);
        }

        return $driver;
    }
}
