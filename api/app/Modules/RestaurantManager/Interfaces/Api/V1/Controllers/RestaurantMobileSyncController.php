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
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.type' => ['required', 'string', 'max:40'],
            'operations.*.idempotency_key' => ['required', 'string', 'max:255'],
            // `present` et non `required` : un tableau VIDE est « empty » pour
            // Laravel, donc `required` rejetait en 422 toute la requête dès
            // qu'une opération avait un payload vide — alors que le contrat
            // est de répondre opération par opération (`error`), sans effet
            // de bord sur les autres. Une opération inconnue ne doit jamais
            // faire échouer le lot entier.
            'operations.*.payload' => ['present', 'array'],
        ]);

        // Borne à 50 opérations par appel : on TRONQUE le surplus au lieu de
        // rejeter la requête. Un client qui a mis 60 opérations en file hors
        // ligne doit voir les 50 premières traitées et repousser le reste,
        // pas perdre tout son lot en 422.
        /** @var list<array{type: string, payload: array<string, mixed>, idempotency_key: string}> $operations */
        $operations = array_values(array_slice($data['operations'], 0, 50));

        $results = $service->sync($actor, $operations);

        return response()->json(['data' => $results]);
    }
}
