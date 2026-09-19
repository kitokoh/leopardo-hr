<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailLocationRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\UpdateRetailLocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des emplacements de stock du module Retail (BC-17 RETAIL, #7673).
 *
 * deny-by-default (RetailLocationPolicy) : lecture membres du tenant,
 * gestion (CRUD) réservée principal/rh. Isolation : toute ressource d'un
 * autre tenant répond 404 (leçon fail-closed #3727).
 */
class RetailLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailLocation::class);

        $query = RetailLocation::query()->where('company_id', $actor->company_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $q = (string) $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('name', 'ilike', '%'.$q.'%')
                ->orWhere('code', 'ilike', '%'.$q.'%'));
        }

        $locations = $query
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($locations->items())
                ->map(fn (RetailLocation $l): array => $this->payload($l)),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'total' => $locations->total(),
            ],
        ]);
    }

    public function store(StoreRetailLocationRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailLocation::class);

        $location = RetailLocation::query()->create([
            'company_id' => $actor->company_id,
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'type' => $request->input('type', 'store'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['data' => $this->payload($location->refresh())], 201);
    }

    public function show(Request $request, RetailLocation $location): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($location->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $location);

        return response()->json(['data' => $this->payload($location)]);
    }

    public function update(UpdateRetailLocationRequest $request, RetailLocation $location): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($location->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $location);

        $location->update([
            'name' => $request->input('name'),
            'code' => $request->input('code', $location->code),
            'type' => $request->input('type') ?? $location->type,
            'is_active' => $request->boolean('is_active', $location->is_active),
        ]);

        return response()->json(['data' => $this->payload($location->refresh())]);
    }

    public function destroy(Request $request, RetailLocation $location): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($location->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $location);

        $location->delete();

        return response()->json(['data' => null], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RetailLocation $location): array
    {
        return [
            'id' => $location->id,
            'company_id' => $location->company_id,
            'name' => $location->name,
            'code' => $location->code,
            'type' => $location->type,
            'is_active' => $location->is_active,
            'created_at' => $location->created_at?->toIso8601String(),
            'updated_at' => $location->updated_at?->toIso8601String(),
        ];
    }
}
