<?php

namespace App\Contracts;

use App\Exceptions\FeatureSynchronizationException;
use App\Models\Feature;
use Illuminate\Support\Collection;

/**
 * Interface pour le registre centralise des fonctionnalites API.
 */
interface FeatureRegistryInterface
{
    /**
     * @throws FeatureSynchronizationException
     */
    public function registerFeature(Feature $feature): void;

    /**
     * @return Collection<int, Feature>
     */
    public function getFeatures(?string $version = null): Collection;

    public function getFeature(string $key): ?Feature;

    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws FeatureSynchronizationException
     */
    public function updateFeature(string $key, array $metadata): void;

    public function removeFeature(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function getManifest(?string $mobileVersion = null): array;

    /**
     * @return Collection<int, Feature>
     */
    public function getCompatibleFeatures(string $mobileVersion): Collection;

    /**
     * @return Collection<int, Feature>
     */
    public function getFeaturesByApiVersion(string $apiVersion): Collection;

    public function hasFeature(string $key): bool;

    public function invalidateCache(?string $key = null): void;

    /**
     * @return array{new: int, updated: int, removed: int, errors: list<string>}
     */
    public function synchronize(): array;

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array;
}
