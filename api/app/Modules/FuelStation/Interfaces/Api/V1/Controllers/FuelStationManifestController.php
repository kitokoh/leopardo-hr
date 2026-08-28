<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Application\Actions\ActivateFuelStation;
use App\Modules\FuelStation\Domain\Exceptions\FuelStationDependencyMissingException;
use App\Modules\FuelStation\Domain\Exceptions\FuelStationException;
use App\Modules\FuelStation\Domain\Models\FuelStationActivation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Manifest et activation de la solution FuelStation (issue #5795).
 *
 * RBAC : api.manager:principal,rh (routes/modules/fuelstation.php).
 * L'activation est idempotente, dépendances refusées si un module de base
 * manque, et le CRM commercial plateforme n'est jamais touché.
 */
class FuelStationManifestController extends Controller
{
    public function __construct(
        private readonly FuelStationManifestService $manifestService,
        private readonly ActivateFuelStation $activateFuelStation,
    ) {}

    public function manifest(): JsonResponse
    {
        $manifest = $this->manifestService->manifest();

        $activation = FuelStationActivation::query()->where('company_id', currentCompany()->id)->first();

        return new JsonResponse([
            'data' => [
                'manifest' => $manifest,
                'activated' => $activation !== null,
                'activation' => $activation === null ? null : [
                    'status' => $activation->status,
                    'manifest_version' => $activation->manifest_version,
                    'activated_at' => $activation->activated_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function activate(): JsonResponse
    {
        try {
            $result = $this->activateFuelStation->execute();
        } catch (FuelStationDependencyMissingException $e) {
            Log::warning('FuelStation: activation refused (missing dependencies)', [
                'company_id' => currentCompany()->id,
                'missing' => $e->missingDependencies(),
            ]);

            return new JsonResponse([
                'error' => $e->errorCode(),
                'missing' => $e->missingDependencies(),
            ], 422);
        } catch (FuelStationException $e) {
            return new JsonResponse(['error' => $e->errorCode()], $e->httpStatus());
        }

        return new JsonResponse(['data' => $result]);
    }

    public function status(): JsonResponse
    {
        $activation = FuelStationActivation::query()->where('company_id', currentCompany()->id)->first();

        return new JsonResponse([
            'data' => [
                'key' => 'fuelstation',
                'activated' => $activation !== null,
                'status' => $activation?->status ?? 'inactive',
                'manifest_version' => $activation?->manifest_version,
                'activated_at' => $activation?->activated_at?->toIso8601String(),
            ],
        ]);
    }
}
