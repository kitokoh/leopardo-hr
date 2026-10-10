<?php

declare(strict_types=1);

namespace App\Core\Feature\Interfaces\Api\V1\Controllers;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour le manifeste des endpoints API mobile
 *
 * BOS-015 (#8202) : injecte ApiEndpointRegistryInterface.
 */
class FeatureManifestController extends Controller
{
    public function __construct(
        private readonly ApiEndpointRegistryInterface $registry,
    ) {}

    /**
     * Récupère le manifeste complet pour l'application mobile
     */
    public function index(Request $request): JsonResponse
    {
        $mobileVersion = $request->header('X-App-Version');
        $apiVersion = (string) $request->header('X-Api-Version', 'v1');

        Log::info('Mobile manifest requested', [
            'app_version' => $mobileVersion,
            'api_version' => $apiVersion,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        $manifest = $this->registry->getManifest($mobileVersion ? (string) $mobileVersion : null);

        return response()->json($manifest)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Manifest-Version', (string) $manifest['version']);
    }

    /**
     * Récupère les fonctionnalités compatibles avec une version mobile spécifique
     */
    public function compatible(string $version): JsonResponse
    {
        $features = $this->registry->getCompatibleFeatures($version);

        return response()->json([
            'version' => $version,
            'total' => $features->count(),
            'features' => $features->map(fn ($f) => $f->toManifestArray())->values(),
        ]);
    }

    /**
     * Récupère les détails d'une fonctionnalité spécifique
     */
    public function show(string $key): JsonResponse
    {
        $feature = $this->registry->getFeature($key);

        if (! $feature) {
            return response()->json([
                'error' => 'Feature not found',
                'message' => "La fonctionnalité '{$key}' n'existe pas.",
            ], 404);
        }

        return response()->json($feature->toManifestArray());
    }

    /**
     * Récupère les statistiques du registre (Admin only)
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->registry->getStatistics();

        return response()->json($stats);
    }

    /**
     * Déclenche une synchronisation manuelle (Admin only)
     */
    public function synchronize(): JsonResponse
    {
        $result = $this->registry->synchronize();

        return response()->json([
            'message' => 'Synchronisation terminée avec succès',
            'result' => $result,
        ]);
    }
}
