<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Infrastructure\Services\Mobile\RestaurantMobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-804 (#6225) — Synchronisation offline mobile (file idempotente).
 *
 * L'app pousse ses opérations effectuées hors ligne ; le serveur les rejoue
 * de façon idempotente (clés client) et répond opération par opération :
 * created | duplicate | error. Borné à 50 opérations par appel (pattern
 * TravelMobileSyncController, TRAVEL-704/#6091).
 */
class RestaurantMobileSyncController extends Controller
{
    public function sync(Request $request, RestaurantMobileSyncService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.type' => ['required', 'string', 'max:40'],
            'operations.*.idempotency_key' => ['required', 'string', 'max:255'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $results = $service->sync($actor, $data['operations']);

        return response()->json(['data' => $results]);
    }
}
