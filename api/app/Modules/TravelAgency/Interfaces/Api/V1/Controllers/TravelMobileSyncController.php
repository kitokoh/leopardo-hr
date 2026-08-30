<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Infrastructure\Services\TravelMobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-704 (#6091) — Synchronisation offline mobile (file idempotente).
 *
 * L\'app agent pousse ses opérations effectuées hors ligne ; le serveur les
 * rejoue de façon idempotente (clés client) et répond opération par
 * opération : created | duplicate | error. Borné à 50 opérations par appel.
 */
class TravelMobileSyncController extends Controller
{
    public function sync(Request $request, TravelMobileSyncService $service): JsonResponse
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
