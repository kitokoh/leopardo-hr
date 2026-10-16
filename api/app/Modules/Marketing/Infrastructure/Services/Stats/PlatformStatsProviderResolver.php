<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Stats;

/**
 * Marketing OS — resolution du provider de statistiques par plateforme
 * (#8530).
 *
 * Retourne `null` (et non une exception) quand aucune plateforme ne sait
 * fournir la donnee : c'est un etat metier normal, que le service traduit en
 * `stats_source = unavailable` avec un motif explicite. Une exception serait
 * ici un abus de langage — l'absence de connecteur n'est pas une panne.
 */
class PlatformStatsProviderResolver
{
    /** @var array<int, PlatformStatsProviderInterface> */
    private array $providers;

    /**
     * @param  iterable<PlatformStatsProviderInterface>  $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers instanceof \Traversable
            ? iterator_to_array($providers)
            : $providers;
    }

    public function resolve(string $platform): ?PlatformStatsProviderInterface
    {
        foreach ($this->providers as $provider) {
            if (in_array($platform, $provider->supportedPlatforms(), true)) {
                return $provider;
            }
        }

        return null;
    }

    /** La plateforme est-elle interrogeable pour des statistiques ? */
    public function supports(string $platform): bool
    {
        return $this->resolve($platform) !== null;
    }
}
