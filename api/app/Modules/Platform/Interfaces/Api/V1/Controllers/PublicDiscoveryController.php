<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Discovery\PublicDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SUPERAPP-P0 — Découverte publique unifiée Leopardo.com.
 *
 * `GET /api/v1/public/discover` : point d'entrée NEUTRE cross-verticale qui
 * agrège les entités publiques de plusieurs verticales (restaurants,
 * hébergements, vitrines) via les providers `PublicDiscoverableProvider`,
 * SANS que le client ait besoin de connaître la verticale.
 *
 * Route isolée SANS auth ni middleware tenant (`throttle:shop-public`), patron
 * BC-27/BC-28. Le service agrégateur n'expose que des entités déjà publiques
 * (fail-closed) et un DTO public strict — aucune donnée interne.
 */
class PublicDiscoveryController extends Controller
{
    public function __construct(private readonly PublicDiscoveryService $discovery) {}

    public function index(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'string', Rule::in($this->discovery->types())],
            'near' => ['sometimes', 'string', 'regex:/^-?\d{1,2}(\.\d+)?,-?\d{1,3}(\.\d+)?$/'],
            'radius_km' => ['sometimes', 'numeric', 'min:0.1', 'max:'.PublicDiscoveryService::MAX_RADIUS_KM],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.PublicDiscoveryService::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json($this->discovery->discover($filters));
    }
}
