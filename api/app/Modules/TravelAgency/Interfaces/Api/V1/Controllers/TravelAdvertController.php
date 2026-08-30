<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-905..908 (#6108..#6111) — Annonces payantes (legacy gv-back,
 * spec §3) : référentiels (types, positions, tarifs) + cycle de vie
 * (soumission, paiement serveur, validation, publication, expiration,
 * renouvellement). Cross-tenant → 404 sûr.
 */
class TravelAdvertController extends Controller
{
    // ── Types d'annonces (TRAVEL-905/#6108) ──────────────────────────────

    public function indexAdvertTypes(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvertType::class)) {
            abort(403);
        }

        $types = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return new JsonResponse(['data' => $types]);
    }

    public function storeAdvertType(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvertType::class)) {
            abort(403);
        }

        $code = trim((string) $request->json('code'));
        $name = trim((string) $request->json('name'));

        if ($code === '' || $name === '') {
            abort(422, 'Advert type code and name are required.');
        }

        $exists = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            abort(422, 'Advert type code already exists for this tenant.');
        }

        $type = TravelAdvertType::query()->create([
            'company_id' => $actor->company_id,
            'code' => $code,
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $type], 201);
    }

    public function updateAdvertType(Request $request, TravelAdvertType $travelAdvertType): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertType->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvertType)) {
            abort(403);
        }

        $name = trim((string) $request->json('name'));

        if ($name === '') {
            abort(422, 'Advert type name is required.');
        }

        $travelAdvertType->update([
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $travelAdvertType->refresh()]);
    }

    public function destroyAdvertType(Request $request, TravelAdvertType $travelAdvertType): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertType->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvertType)) {
            abort(403);
        }

        $travelAdvertType->delete();

        return new JsonResponse(null, 204);
    }

    // ── Positions de publication (TRAVEL-905/#6108) ───────────────────────

    public function indexAdvertPositions(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvertPosition::class)) {
            abort(403);
        }

        $positions = TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return new JsonResponse(['data' => $positions]);
    }

    public function storeAdvertPosition(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvertPosition::class)) {
            abort(403);
        }

        $code = trim((string) $request->json('code'));
        $name = trim((string) $request->json('name'));

        if ($code === '' || $name === '') {
            abort(422, 'Advert position code and name are required.');
        }

        $exists = TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            abort(422, 'Advert position code already exists for this tenant.');
        }

        $position = TravelAdvertPosition::query()->create([
            'company_id' => $actor->company_id,
            'code' => $code,
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $position], 201);
    }

    public function updateAdvertPosition(Request $request, TravelAdvertPosition $travelAdvertPosition): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPosition->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvertPosition)) {
            abort(403);
        }

        $name = trim((string) $request->json('name'));

        if ($name === '') {
            abort(422, 'Advert position name is required.');
        }

        $travelAdvertPosition->update([
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $travelAdvertPosition->refresh()]);
    }

    public function destroyAdvertPosition(Request $request, TravelAdvertPosition $travelAdvertPosition): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPosition->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvertPosition)) {
            abort(403);
        }

        $travelAdvertPosition->delete();

        return new JsonResponse(null, 204);
    }
}
