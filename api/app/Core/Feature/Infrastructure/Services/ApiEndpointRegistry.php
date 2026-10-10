<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Contracts\FeatureDetectorInterface;
use App\Contracts\FeatureRegistryInterface;
use App\Exceptions\FeatureSynchronizationException;
use App\Modules\Billing\Domain\Models\Feature;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BOS-015 (#8202) — Registre centralisé des endpoints API pour le manifeste mobile.
 *
 * Maintient l'inventaire des endpoints API déclarés pour les applications mobiles
 * (versioning, compatibilité mobile_version_min/max, réponse schéma).
 */
class ApiEndpointRegistry implements ApiEndpointRegistryInterface, FeatureRegistryInterface
{
    private const CACHE_PREFIX = 'feature_registry';

    private const CACHE_TTL = 3600; // 1 heure

    private const MANIFEST_CACHE_KEY = self::CACHE_PREFIX.':manifest';

    private const FEATURES_CACHE_KEY = self::CACHE_PREFIX.':features';

    private const STATISTICS_CACHE_KEY = self::CACHE_PREFIX.':statistics';

    public function __construct(
        private readonly FeatureDetectorInterface $detector,
        private readonly CacheManager $cache
    ) {}

    /**
     * {@inheritdoc}
     */
    public function registerFeature(Feature $feature): void
    {
        try {
            DB::beginTransaction();

            $existingFeature = Feature::withoutGlobalScope('company')->where('key', $feature->key)->first();

            if ($existingFeature) {
                $existingFeature->update($feature->toArray());
                Log::info('Feature updated in registry', ['key' => $feature->key]);
            } else {
                $feature->save();
                Log::info('Feature registered in registry', ['key' => $feature->key]);
            }

            DB::commit();
            $this->invalidateCache($feature->key);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to register feature in registry', [
                'key' => $feature->key,
                'error' => $e->getMessage(),
            ]);

            throw new FeatureSynchronizationException(
                "Failed to register feature {$feature->key}: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return Collection<int, Feature>
     */
    public function getFeatures(?string $version = null): Collection
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, $version);

        /** @var Collection<int, Feature> $result */
        $result = $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($version): Collection {
            $query = Feature::withoutGlobalScope('company')->active();

            if ($version) {
                $query->where('api_version', $version);
            }

            return $query->orderBy('key')->get();
        });

        return $result;
    }

    public function getFeature(string $key): ?Feature
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'single', $key);

        /** @var Feature|null $result */
        $result = $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($key): ?Feature {
            return Feature::withoutGlobalScope('company')
                ->where('key', $key)
                ->first();
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFeature(string $key, array $metadata): void
    {
        try {
            DB::beginTransaction();

            /** @var Feature $feature */
            $feature = Feature::withoutGlobalScope('company')->where('key', $key)->firstOrFail();
            $feature->update($metadata);

            DB::commit();
            $this->invalidateCache($key);

            Log::info('Feature metadata updated in registry', ['key' => $key]);
        } catch (ModelNotFoundException) {
            DB::rollBack();
            Log::warning('Attempted to update non-existent feature', ['key' => $key]);

            throw new FeatureSynchronizationException("Feature {$key} not found");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update feature metadata', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            throw new FeatureSynchronizationException(
                "Failed to update feature {$key}: {$e->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeFeature(string $key): void
    {
        try {
            DB::beginTransaction();

            $deleted = Feature::withoutGlobalScope('company')->where('key', $key)->delete();

            if (! $deleted) {
                Log::warning('Attempted to remove non-existent feature', ['key' => $key]);

                return;
            }

            DB::commit();
            $this->invalidateCache($key);

            Log::info('Feature removed from registry', ['key' => $key]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove feature from registry', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            throw new FeatureSynchronizationException(
                "Failed to remove feature {$key}: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getManifest(?string $mobileVersion = null): array
    {
        $cacheKey = $this->buildCacheKey(self::MANIFEST_CACHE_KEY, $mobileVersion);

        /** @var array<string, mixed> $manifest */
        $manifest = $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($mobileVersion): array {
            $features = $mobileVersion
                ? $this->getCompatibleFeatures($mobileVersion)
                : $this->getFeatures();

            $data = [
                'version' => $this->getCurrentApiVersion(),
                'generated_at' => Carbon::now()->toIso8601String(),
                'mobile_version_min' => $this->getMinimumMobileVersion(),
                'mobile_version_target' => $mobileVersion,
                'total_features' => $features->count(),
                'features' => $features->map(fn (Feature $feature) => $feature->toManifestArray())->toArray(),
            ];

            Log::info('Manifest generated', [
                'mobile_version' => $mobileVersion,
                'feature_count' => $features->count(),
            ]);

            return $data;
        });

        return $manifest;
    }

    /**
     * @return Collection<int, Feature>
     */
    public function getCompatibleFeatures(string $mobileVersion): Collection
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'compatible', $mobileVersion);

        /** @var Collection<int, Feature> $result */
        $result = $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($mobileVersion): Collection {
            return Feature::withoutGlobalScope('company')
                ->active()
                ->compatibleWith($mobileVersion)
                ->orderBy('title')
                ->get();
        });

        return $result;
    }

    /**
     * @return Collection<int, Feature>
     */
    public function getFeaturesByApiVersion(string $apiVersion): Collection
    {
        return $this->getFeatures($apiVersion);
    }

    /**
     * {@inheritdoc}
     */
    public function hasFeature(string $key): bool
    {
        return $this->getFeature($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateCache(?string $key = null): void
    {
        if ($key) {
            $patterns = [
                $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'single', $key),
            ];
        } else {
            $patterns = [
                self::MANIFEST_CACHE_KEY.'*',
                self::FEATURES_CACHE_KEY.'*',
                self::STATISTICS_CACHE_KEY.'*',
            ];
        }

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->cache->tags([self::CACHE_PREFIX])->flush();
            } else {
                $this->cache->forget($pattern);
            }
        }

        Log::debug('Api endpoint registry cache invalidated', ['key' => $key]);
    }

    /**
     * {@inheritdoc}
     */
    public function synchronize(): array
    {
        Log::info('Starting api endpoint registry synchronization');

        try {
            DB::beginTransaction();

            $result = [
                'new' => 0,
                'updated' => 0,
                'removed' => 0,
                'errors' => [],
            ];

            $newFeatures = $this->detector->detectNewFeatures();
            foreach ($newFeatures as $featureData) {
                try {
                    /** @var array<string, mixed> $featureData */
                    $feature = new Feature($featureData);
                    $this->registerFeature($feature);
                    $result['new']++;
                } catch (\Exception $e) {
                    $result['errors'][] = 'Failed to register new feature: '.$e->getMessage();
                }
            }

            $changes = $this->detector->detectChanges();
            foreach ($changes as $change) {
                try {
                    /** @var array{type: string, feature_key: string, current_metadata?: array<string, mixed>} $change */
                    $featureKey = (string) $change['feature_key'];

                    switch ($change['type']) {
                        case 'modified':
                            /** @var array<string, mixed> $currentMetadata */
                            $currentMetadata = $change['current_metadata'] ?? [];
                            $this->updateFeature($featureKey, $currentMetadata);
                            $result['updated']++;
                            break;
                        case 'removed':
                            $this->removeFeature($featureKey);
                            $result['removed']++;
                            break;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = 'Failed to process change: '.$e->getMessage();
                }
            }

            DB::commit();

            $this->invalidateCache();

            Log::info('Api endpoint registry synchronization completed', $result);

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Api endpoint registry synchronization failed', ['error' => $e->getMessage()]);

            throw new FeatureSynchronizationException(
                "Synchronization failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        /** @var array<string, mixed> $stats */
        $stats = $this->cache->remember(self::STATISTICS_CACHE_KEY, self::CACHE_TTL, function (): array {
            /** @var \Illuminate\Database\Eloquent\Builder<Feature> $baseQuery */
            $baseQuery = Feature::withoutGlobalScope('company')->newQuery();

            $totalFeatures = (clone $baseQuery)->count();
            $activeFeatures = (clone $baseQuery)->active()->count();

            /** @var array<string, int> $byApiVersion */
            $byApiVersion = (clone $baseQuery)->select('api_version', DB::raw('count(*) as count'))
                ->groupBy('api_version')
                ->pluck('count', 'api_version')
                ->toArray();

            /** @var array<string, int> $byStatus */
            $byStatus = (clone $baseQuery)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $recentlyUpdated = (clone $baseQuery)->where('updated_at', '>=', Carbon::now()->subDays(7))
                ->count();

            return [
                'total_features' => $totalFeatures,
                'active_features' => $activeFeatures,
                'inactive_features' => $totalFeatures - $activeFeatures,
                'by_api_version' => $byApiVersion,
                'by_status' => $byStatus,
                'recently_updated' => $recentlyUpdated,
                'last_synchronization' => $this->getLastSynchronizationTime(),
                'cache_status' => $this->getCacheStatus(),
            ];
        });

        return $stats;
    }

    private function buildCacheKey(?string ...$parts): string
    {
        return implode(':', array_filter($parts));
    }

    private function getCurrentApiVersion(): string
    {
        return (string) config('app.api_version', 'v1');
    }

    private function getMinimumMobileVersion(): string
    {
        return (string) (Feature::withoutGlobalScope('company')->min('mobile_version_min') ?? '1.0.0');
    }

    private function getLastSynchronizationTime(): ?string
    {
        $lastSync = $this->cache->get(self::CACHE_PREFIX.':last_sync');

        return $lastSync ? Carbon::parse((string) $lastSync)->toIso8601String() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCacheStatus(): array
    {
        $manifestCached = $this->cache->has(self::MANIFEST_CACHE_KEY);
        $featuresCached = $this->cache->has(self::FEATURES_CACHE_KEY);

        return [
            'manifest_cached' => $manifestCached,
            'features_cached' => $featuresCached,
            'cache_driver' => config('cache.default'),
        ];
    }
}
