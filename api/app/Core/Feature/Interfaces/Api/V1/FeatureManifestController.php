<?php

declare(strict_types=1);

namespace App\Core\Feature\Interfaces\Api\V1;

use App\Contracts\FeatureRegistryInterface;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour l'API du manifeste des fonctionnalités
 */
class FeatureManifestController extends Controller
{
    public function __construct(
        private readonly FeatureRegistryInterface $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            /** @var string|null $mobileVersion */
            $mobileVersion = $request->query('mobile_version', '1.0.0');
            /** @var Employee $user */
            $user = Auth::user();

            Log::info('Feature manifest requested', [
                'user_id'       => $user->id,
                'mobile_version' => $mobileVersion,
                'user_agent'    => $request->userAgent(),
            ]);

            /** @var array<string, mixed> $manifest */
            $manifest = $this->registry->getManifest(is_string($mobileVersion) ? $mobileVersion : '1.0.0');

            /** @var array<int|string, mixed> $features */
            $features = is_array($manifest['features'] ?? null) ? $manifest['features'] : [];
            $manifest['features']       = $this->filterFeaturesByPermissions($features, $user);
            $manifest['total_features'] = count($manifest['features']);
            $manifest['user_id']        = $user->id;
            $manifest['user_role']      = $user->role ?? 'employee';

            return response()->json([
                'success' => true,
                'data'    => $manifest,
                'meta'    => [
                    'generated_for_user' => $user->id,
                    'mobile_version'     => $mobileVersion,
                    'api_version'        => config('app.api_version', 'v1'),
                    'cache_ttl'          => 3600,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate feature manifest', [
                'user_id'        => Auth::id(),
                'mobile_version' => $request->query('mobile_version'),
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate feature manifest',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function compatible(Request $request, string $version): JsonResponse
    {
        try {
            /** @var Employee $user */
            $user = Auth::user();

            $features = $this->registry->getCompatibleFeatures($version);

            /** @var array<int|string, mixed> $featuresArray */
            $featuresArray    = $features->map(fn (mixed $feature): mixed => $feature->toManifestArray())->toArray();
            $filteredFeatures = $this->filterFeaturesByPermissions($featuresArray, $user);

            return response()->json([
                'success' => true,
                'data'    => [
                    'mobile_version' => $version,
                    'total_features' => count($filteredFeatures),
                    'features'       => $filteredFeatures,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get compatible features', [
                'user_id' => Auth::id(),
                'version' => $version,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get compatible features',
            ], 500);
        }
    }

    public function show(string $key): JsonResponse
    {
        try {
            $feature = $this->registry->getFeature($key);

            if (! $feature) {
                return response()->json(['success' => false, 'message' => 'Feature not found'], 404);
            }

            /** @var Employee $user */
            $user = Auth::user();

            /** @var array<string, mixed> $permissions */
            $permissions = is_array($feature->permissions ?? null) ? $feature->permissions : [];

            if (! $this->userHasFeaturePermissions($user, $permissions)) {
                return response()->json(['success' => false, 'message' => 'Insufficient permissions'], 403);
            }

            return response()->json(['success' => true, 'data' => $feature->toManifestArray()]);
        } catch (\Exception $e) {
            Log::error('Failed to get feature', ['user_id' => Auth::id(), 'feature_key' => $key, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to get feature'], 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            /** @var Employee $user */
            $user = Auth::user();

            if (! $this->userIsAdmin($user)) {
                return response()->json(['success' => false, 'message' => 'Admin access required'], 403);
            }

            $stats = $this->registry->getStatistics();

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get registry statistics', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to get statistics'], 500);
        }
    }

    public function synchronize(): JsonResponse
    {
        try {
            /** @var Employee $user */
            $user = Auth::user();

            if (! $this->userIsAdmin($user)) {
                return response()->json(['success' => false, 'message' => 'Admin access required'], 403);
            }

            Log::info('Manual feature registry synchronization initiated', ['user_id' => $user->id]);

            $result = $this->registry->synchronize();

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'Synchronization completed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to synchronize registry', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to synchronize registry'], 500);
        }
    }

    /**
     * @param array<int|string, mixed> $features
     * @param Employee|Authenticatable $user
     * @return array<int|string, mixed>
     */
    private function filterFeaturesByPermissions(array $features, Employee|Authenticatable $user): array
    {
        return array_values(array_filter($features, function (mixed $feature) use ($user): bool {
            /** @var array<string, mixed> $featureArr */
            $featureArr          = is_array($feature) ? $feature : [];
            // Feature::toManifestArray() expose la cle 'permissions' (pas
            // 'required_permissions', qui n'existe sur aucun modele de ce module).
            $requiredPermissions = is_array($featureArr['permissions'] ?? null)
                ? $featureArr['permissions']
                : [];

            if (empty($requiredPermissions)) {
                return true;
            }

            return $this->userHasFeaturePermissions($user, $requiredPermissions);
        }));
    }

    /**
     * @param array<int|string, mixed> $requiredPermissions
     */
    private function userHasFeaturePermissions(Employee|Authenticatable $user, array $requiredPermissions): bool
    {
        if (empty($requiredPermissions)) {
            return true;
        }

        if ($this->userIsAdmin($user)) {
            return true;
        }

        foreach ($requiredPermissions as $permission) {
            if (! $user->can((string) $permission)) {
                return false;
            }
        }

        return true;
    }

    private function userIsAdmin(Employee|Authenticatable $user): bool
    {
        if ($user instanceof Employee) {
            return $user->hasManagerRole('principal') || ($user->role ?? '') === 'admin';
        }

        return false;
    }
}
