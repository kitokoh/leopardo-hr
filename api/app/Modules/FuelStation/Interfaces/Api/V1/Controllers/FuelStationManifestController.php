<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Manifest\FuelStationManifest;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationException;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manifest et activation FuelStation — Issue #5795 (FUEL-001).
 *
 * RBAC : `api.manager:principal,rh` (lecture manifest), activation réservée
 * `principal` (Policy vérifiée via le middleware + garde manager_role).
 * Isolation tenant : les flags vivent sur `companies.features` (tenant) —
 * aucune donnée cross-tenant lisible.
 */
class FuelStationManifestController extends Controller
{
    public function __construct(
        private readonly FuelStationActivationService $activation,
    ) {
    }

    public function manifest(Request $request): JsonResponse
    {
        /** @var Company $company */
        $company = currentCompany();

        return response()->json([
            'data' => [
                'key' => FuelStationManifest::KEY,
                'maturity' => FuelStationManifest::MATURITY,
                'active' => $this->activation->isActive($company),
                'requires' => FuelStationManifest::REQUIRED_DEPENDENCIES,
                'integrates_with' => FuelStationManifest::OPTIONAL_INTEGRATIONS,
                'manager_roles' => FuelStationManifest::MANAGER_ROLES,
                'features' => array_map(
                    static fn (array $definition): array => [
                        'title' => $definition['title'],
                        'endpoint' => $definition['endpoint'],
                        'methods' => $definition['methods'],
                        'permissions' => $definition['permissions'],
                    ],
                    FuelStationManifest::FEATURES
                ),
            ],
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasManagerRole') && ! $user->hasManagerRole('principal')) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        /** @var Company $company */
        $company = currentCompany();

        try {
            $firstTime = $this->activation->activate($company);
        } catch (FuelStationActivationException $exception) {
            return response()->json([
                'error' => 'FUEL_STATION_ACTIVATION_REFUSED',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'key' => FuelStationManifest::KEY,
                'active' => $this->activation->isActive($company),
                'activated' => $firstTime,
            ],
        ], $firstTime ? 201 : 200);
    }
}
