<?php

namespace App\Services;

use App\Contracts\FeatureDetectorInterface;
use App\Contracts\FeatureRegistryInterface;
use App\Exceptions\FeatureSynchronizationException;
use App\Models\Feature;
use Carbon\Carbon;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation du registre centralisé des fonctionnalités API
 *
 * Maintient un inventaire complet de toutes les fonctionnalités API disponibles
 * avec système de cache intelligent et support du versioning.
 */
class FeatureRegistry implements FeatureRegistryInterface
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

            // Vérifier si la fonctionnalité existe déjà
            $existingFeature = Feature::where('key', $feature->key)->first();

            if ($existingFeature) {
                // Mettre à jour la fonctionnalité existante
                $existingFeature->update($feature->toArray());
                Log::info('Feature updated in registry', ['key' => $feature->key]);
            } else {
                // Créer une nouvelle fonctionnalité
                $feature->save();
                Log::info('Feature registered in registry', ['key' => $feature->key]);
            }

            DB::commit();

            // Invalider le cache
            $this->invalidateCache();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to register feature', [
                'key' => $feature->key,
                'error' => $e->getMessage(),
            ]);
            throw new FeatureSynchronizationException(
                "Failed to register feature {$feature->key}: {$e->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFeatures(?string $version = null): Collection
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, $version);

        /** @var Collection<int, Feature> */
        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($version) {
            $query = Feature::active();

            if ($version) {
                $query->forApiVersion($version);
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, Feature> $features */
            $features = $query->orderBy('title')->get();

            Log::debug('Features retrieved from database', [
                'count' => $features->count(),
                'version' => $version,
            ]);

            return $features;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getFeature(string $key): ?Feature
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'single', $key);

        /** @var Feature|null */
        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            /** @var Feature|null */
            return Feature::where('key', $key)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function updateFeature(string $key, array $metadata): void
    {
        try {
            DB::beginTransaction();

            $feature = Feature::where('key', $key)->firstOrFail();

            // Fusionner les nouvelles métadonnées avec les existantes
            $updatedMetadata = array_merge($feature->metadata ?? [], $metadata);

            $feature->update([
                'metadata' => $updatedMetadata,
                'updated_at' => now(),
            ]);

            DB::commit();

            Log::info('Feature metadata updated', ['key' => $key]);

            // Invalider le cache pour cette fonctionnalité
            $this->invalidateCache($key);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::warning('Attempted to update non-existent feature', ['key' => $key]);
            throw new FeatureSynchronizationException(
                "Feature {$key} not found for update"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update feature', [
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

            /** @var int $deleted */
            $deleted = Feature::where('key', $key)->delete();

            if ($deleted > 0) {
                Log::info('Feature removed from registry', ['key' => $key]);
            } else {
                Log::warning('Attempted to remove non-existent feature', ['key' => $key]);
            }

            DB::commit();

            // Invalider le cache
            $this->invalidateCache($key);
            $this->invalidateCache();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove feature', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw new FeatureSynchronizationException(
                "Failed to remove feature {$key}: {$e->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getManifest(?string $mobileVersion = null): array
    {
        $cacheKey = $this->buildCacheKey(self::MANIFEST_CACHE_KEY, $mobileVersion);

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($mobileVersion) {
            $features = $this->getCompatibleFeatures($mobileVersion ?? '1.0.0');

            $featuresData = $features->map(fn (Feature $feature) => $feature->toManifestArray())->toArray();

            // Calculer une signature simple pour satisfaire le contrat mobile
            // En production, cela utiliserait une clé privée asymétrique
            $signature = hash('sha256', json_encode($featuresData));

            $manifest = [
                'version' => $this->getCurrentApiVersion(),
                'generated_at' => Carbon::now()->toISOString(),
                'mobile_version_min' => $this->getMinimumMobileVersion(),
                'mobile_version_target' => $mobileVersion,
                'total_features' => $features->count(),
                'signature' => $signature,
                'features' => $featuresData,
            ];

            Log::info('Manifest generated', [
                'mobile_version' => $mobileVersion,
                'feature_count' => $features->count(),
            ]);

            return $manifest;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getCompatibleFeatures(string $mobileVersion): Collection
    {
        $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'compatible', $mobileVersion);

        /** @var Collection<int, Feature> */
        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($mobileVersion) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Feature> */
            return Feature::active()
                ->compatibleWith($mobileVersion)
                ->orderBy('title')
                ->get();
        });
    }

    /**
     * {@inheritdoc}
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
            // Invalider le cache pour une fonctionnalité spécifique
            $cacheKey = $this->buildCacheKey(self::FEATURES_CACHE_KEY, 'single', $key);
            $this->cache->forget($cacheKey);
        } else {
            // Invalider tout le cache du registre
            // On utilise forget sur les clés de base si le driver ne supporte pas les tags
            $this->cache->forget(self::STATISTICS_CACHE_KEY);

            try {
                // On essaie d'utiliser les tags si possible (Redis/Memcached)
                $this->cache->tags([self::CACHE_PREFIX])->flush();
            } catch (\BadMethodCallException $e) {
                // Si le driver (ex: file, database) ne supporte pas les tags, on ignore
                Log::debug('Cache tags not supported by driver', ['driver' => config('cache.default')]);
            }
        }

        Log::debug('Feature registry cache invalidated', ['key' => $key]);
    }

    /**
     * {@inheritdoc}
     */
    public function synchronize(): array
    {
        Log::info('Starting feature registry synchronization');

        try {
            DB::beginTransaction();

            $result = [
                'new' => 0,
                'updated' => 0,
                'removed' => 0,
                'errors' => [],
            ];

            // Détecter les nouvelles fonctionnalités
            $newFeatures = $this->detector->detectNewFeatures();
            foreach ($newFeatures as $featureData) {
                try {
                    $feature = new Feature($featureData);
                    $this->registerFeature($feature);
                    $result['new']++;
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to register new feature: {$e->getMessage()}";
                }
            }

            // Détecter les changements
            $changes = $this->detector->detectChanges();
            foreach ($changes as $change) {
                try {
                    switch ($change['type']) {
                        case 'modified':
                            $this->updateFeature(
                                $change['feature_key'],
                                $change['current_metadata']
                            );
                            $result['updated']++;
                            break;

                        case 'removed':
                            $this->removeFeature($change['feature_key']);
                            $result['removed']++;
                            break;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to process change for {$change['feature_key']}: {$e->getMessage()}";
                }
            }

            DB::commit();

            // Invalider tout le cache après synchronisation
            $this->invalidateCache();

            Log::info('Feature registry synchronization completed', $result);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Feature registry synchronization failed', ['error' => $e->getMessage()]);
            throw new FeatureSynchronizationException(
                "Synchronization failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(): array
    {
        return $this->cache->remember(self::STATISTICS_CACHE_KEY, self::CACHE_TTL, function () {
            $totalFeatures = Feature::count();
            $activeFeatures = Feature::active()->count();

            $byApiVersion = Feature::select('api_version', DB::raw('count(*) as count'))
                ->groupBy('api_version')
                ->pluck('count', 'api_version')
                ->toArray();

            $byStatus = Feature::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $recentlyUpdated = Feature::where('updated_at', '>=', Carbon::now()->subDays(7))
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
    }

    /**
     * Construit une clé de cache avec préfixe et paramètres
     */
    private function buildCacheKey(?string ...$parts): string
    {
        return implode(':', array_filter($parts));
    }

    /**
     * Récupère la version actuelle de l'API
     */
    private function getCurrentApiVersion(): string
    {
        return config('app.api_version', 'v1');
    }

    /**
     * Récupère la version mobile minimale supportée
     */
    private function getMinimumMobileVersion(): string
    {
        return Feature::min('mobile_version_min') ?? '1.0.0';
    }

    /**
     * Récupère l'heure de la dernière synchronisation
     */
    private function getLastSynchronizationTime(): ?string
    {
        $lastSync = $this->cache->get(self::CACHE_PREFIX.':last_sync');

        return $lastSync ? Carbon::parse($lastSync)->toISOString() : null;
    }

    /**
     * Récupère le statut du cache
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
