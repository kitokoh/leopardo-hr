<?php

namespace App\Http\Controllers\Api;

use App\Contracts\FeatureRegistryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeatureManifestController extends Controller
{
    public function __construct(
        private readonly FeatureRegistryInterface $registry,
    ) {}

    /**
     * Safely get authenticated user
     */
    private function getUser()
    {
        return Auth::user();
    }

    /**
     * Récupère le manifeste complet des fonctionnalités
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $mobileVersion = $request->query('mobile_version', '1.0.0');
            $user = $this->getUser();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            Log::info('Feature manifest requested', [
                'user_id' => $user->id,
                'mobile_version' => $mobileVersion,
                'user_agent' => $request->userAgent(),
            ]);

            $manifest = $this->registry->getManifest($mobileVersion);

            $manifest['features'] = $this->filterFeaturesByPermissions(
                $manifest['features'],
                $user
            );

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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function compatible(Request $request, string $version): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $features = $this->registry->getCompatibleFeatures($version);

            $featuresArray = $features->map(
                fn ($feature) => $feature->toManifestArray()
            )->toArray();

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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function show(string $key): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $feature = $this->registry->getFeature($key);

            if (! $feature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feature not found',
                ], 404);
            }

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
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (! $this->userIsAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $this->registry->getStatistics(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get registry statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function synchronize(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (! $this->userIsAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            $result = $this->registry->synchronize();

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Synchronization completed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to synchronize registry', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    private function filterFeaturesByPermissions(array $features, $user): array
    {
        return array_filter($features, function ($feature) use ($user) {
            return $this->userHasFeaturePermissions($user, $feature['permissions'] ?? []);
        });
    }

    private function userHasFeaturePermissions($user, array $permissions): bool
    {
        if (empty($permissions)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    private function userIsAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $role = $user->role ?? '';

        return in_array($role, ['admin', 'super_admin', 'manager'], true);
    }
}
