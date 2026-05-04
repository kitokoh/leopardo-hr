<?php

namespace App\Http\Controllers\Api;

use App\Contracts\FeatureRegistryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour l'API du manifeste des fonctionnalités
 *
 * Expose les endpoints pour que l'application mobile puisse récupérer
 * le manifeste des fonctionnalités disponibles et compatibles.
 */
class FeatureManifestController extends Controller
{
    public function __construct(
        private readonly FeatureRegistryInterface $registry,
    ) {}

    /**
     * Récupère le manifeste complet des fonctionnalités
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $mobileVersion = $request->query('mobile_version', '1.0.0');
            $user = Auth::user();

            Log::info('Feature manifest requested', [
                'user_id' => $user->id,
                'mobile_version' => $mobileVersion,
                'user_agent' => $request->userAgent(),
            ]);

            // Générer le manifeste pour la version mobile demandée
            $manifest = $this->registry->getManifest($mobileVersion);

            // Filtrer les fonctionnalités selon les permissions de l'utilisateur
            $manifest['features'] = $this->filterFeaturesByPermissions(
                $manifest['features'],
                $user
            );

            // Mettre à jour le nombre total après filtrage
            $manifest['total_features'] = count($manifest['features']);
            $manifest['user_id'] = $user->id;
            $manifest['user_role'] = $user->role ?? 'employee';

            return response()->json([
                'success' => true,
                'data' => $manifest,
                'meta' => [
                    'generated_for_user' => $user->id,
                    'mobile_version' => $mobileVersion,
                    'api_version' => config('app.api_version', 'v1'),
                    'cache_ttl' => 3600,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate feature manifest', [
                'user_id' => Auth::id(),
                'mobile_version' => $request->query('mobile_version'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate feature manifest',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Récupère les fonctionnalités compatibles avec une version mobile
     */
    public function compatible(Request $request, string $version): JsonResponse
    {
        try {
            $user = Auth::user();

            $features = $this->registry->getCompatibleFeatures($version);

            // Convertir en format manifeste et filtrer par permissions
            $featuresArray = $features->map(fn ($feature) => $feature->toManifestArray())->toArray();
            $filteredFeatures = $this->filterFeaturesByPermissions($featuresArray, $user);

            return response()->json([
                'success' => true,
                'data' => [
                    'mobile_version' => $version,
                    'total_features' => count($filteredFeatures),
                    'features' => $filteredFeatures,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get compatible features', [
                'user_id' => Auth::id(),
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get compatible features',
            ], 500);
        }
    }

    /**
     * Récupère une fonctionnalité spécifique
     */
    public function show(string $key): JsonResponse
    {
        try {
            $feature = $this->registry->getFeature($key);

            if (! $feature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feature not found',
                ], 404);
            }

            $user = Auth::user();

            // Vérifier les permissions
            if (! $this->userHasFeaturePermissions($user, $feature->permissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $feature->toManifestArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get feature', [
                'user_id' => Auth::id(),
                'feature_key' => $key,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get feature',
            ], 500);
        }
    }

    /**
     * Récupère les statistiques du registre (admin seulement)
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur est admin
            if (! $this->userIsAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            $stats = $this->registry->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get registry statistics', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
            ], 500);
        }
    }

    /**
     * Synchronise le registre (admin seulement)
     */
    public function synchronize(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur est admin
            if (! $this->userIsAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            Log::info('Manual feature registry synchronization initiated', [
                'user_id' => $user->id,
            ]);

            $result = $this->registry->synchronize();

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Synchronization completed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to synchronize registry', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Synchronization failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Filtre les fonctionnalités selon les permissions de l'utilisateur
     *
     * @param  mixed  $user
     */
    private function filterFeaturesByPermissions(array $features, $user): array
    {
        return array_filter($features, function ($feature) use ($user) {
            return $this->userHasFeaturePermissions($user, $feature['permissions'] ?? []);
        });
    }

    /**
     * Vérifie si l'utilisateur a les permissions pour une fonctionnalité
     *
     * @param  mixed  $user
     */
    private function userHasFeaturePermissions($user, array $requiredPermissions): bool
    {
        // Si aucune permission requise, la fonctionnalité est accessible
        if (empty($requiredPermissions)) {
            return true;
        }

        // Vérifier chaque permission requise
        foreach ($requiredPermissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     *
     * @param  mixed  $user
     */
    private function userIsAdmin($user): bool
    {
        // Adapter selon votre système de rôles
        return $user->role === 'admin' || $user->role === 'super_admin';
    }
}
