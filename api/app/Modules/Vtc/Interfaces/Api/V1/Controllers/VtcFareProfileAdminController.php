<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Exceptions\DomainException;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\FareProfileRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcFareProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * CRUD des grilles tarifaires (BC-34 VTC, VTC-06/#8362, rôle vtc.admin).
 *
 * Une seule grille `is_default` par tenant : poser le drapeau sur une
 * grille le retire des autres dans la même transaction. La suppression
 * d'une grille référencée par des courses est refusée (409
 * VTC_FARE_PROFILE_IN_USE — les courses gardent leur devis historisé).
 */
final class VtcFareProfileAdminController
{
    public function index(): AnonymousResourceCollection
    {
        return VtcFareProfileResource::collection(
            VtcFareProfile::query()->orderByDesc('is_default')->orderBy('name')->get()
        );
    }

    public function store(FareProfileRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $profile = DB::transaction(function () use ($validated): VtcFareProfile {
            /** @var VtcFareProfile $profile */
            $profile = VtcFareProfile::query()->create([
                'name' => $validated['name'],
                'currency' => strtoupper((string) $validated['currency']),
                'base_minor' => $validated['base_minor'],
                'per_km_minor' => $validated['per_km_minor'],
                'per_minute_minor' => $validated['per_minute_minor'],
                'minimum_minor' => $validated['minimum_minor'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            $this->enforceSingleDefault($profile);

            return $profile;
        });

        return (new VtcFareProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(FareProfileRequest $request, int $id): VtcFareProfileResource
    {
        $profile = $this->findProfile($id);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        DB::transaction(function () use ($profile, $validated): void {
            $profile->forceFill([
                'name' => $validated['name'],
                'currency' => strtoupper((string) $validated['currency']),
                'base_minor' => $validated['base_minor'],
                'per_km_minor' => $validated['per_km_minor'],
                'per_minute_minor' => $validated['per_minute_minor'],
                'minimum_minor' => $validated['minimum_minor'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ])->save();

            $this->enforceSingleDefault($profile);
        });

        return new VtcFareProfileResource($profile->refresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $profile = $this->findProfile($id);

        if ($profile->rides()->exists()) {
            throw new DomainException(
                (string) __('vtc.fare_profile_delete_in_use'),
                409,
                'VTC_FARE_PROFILE_IN_USE'
            );
        }

        $profile->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Exclusivité du drapeau `is_default` par tenant (transaction englobante).
     */
    private function enforceSingleDefault(VtcFareProfile $profile): void
    {
        if (! $profile->is_default) {
            return;
        }

        VtcFareProfile::query()
            ->whereKeyNot($profile->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function findProfile(int $id): VtcFareProfile
    {
        /** @var VtcFareProfile|null $profile */
        $profile = VtcFareProfile::query()->whereKey($id)->first();

        if (! $profile instanceof VtcFareProfile) {
            abort(404);
        }

        return $profile;
    }
}
