<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

use RuntimeException;

/**
 * Module Marketing — Phase 3 (issue #1433).
 *
 * Resout, pour une plateforme cible (cle `target_platforms` d'un
 * `SocialPost`), le `SocialPublisherInterface` charge de la publier.
 * Utilise par `SocialPublishingService::publishNow()` pour grouper les
 * plateformes d'un post par publisher avant l'appel Ayrshare.
 */
class SocialPublisherResolver
{
    /** @var array<int, SocialPublisherInterface> */
    private array $publishers;

    /**
     * @param  iterable<SocialPublisherInterface>  $publishers
     */
    public function __construct(iterable $publishers)
    {
        $this->publishers = $publishers instanceof \Traversable
            ? iterator_to_array($publishers)
            : $publishers;
    }

    public function resolve(string $platform): SocialPublisherInterface
    {
        foreach ($this->publishers as $publisher) {
            if (in_array($platform, $publisher->supportedPlatforms(), true)) {
                return $publisher;
            }
        }

        throw new RuntimeException("Marketing: aucun publisher configure pour la plateforme '{$platform}'.");
    }

    /**
     * Regroupe une liste de plateformes cibles par publisher resolu, en
     * conservant l'ordre d'apparition des plateformes au sein de chaque
     * groupe.
     *
     * @param  array<int, string>  $platforms
     * @return array<int, array{publisher: SocialPublisherInterface, platforms: array<int, string>}>
     */
    public function groupByPublisher(array $platforms): array
    {
        /** @var array<int, array{publisher: SocialPublisherInterface, platforms: array<int, string>}> $groups */
        $groups = [];

        foreach ($platforms as $platform) {
            $publisher = $this->resolve($platform);
            $key = spl_object_id($publisher);

            if (! isset($groups[$key])) {
                $groups[$key] = ['publisher' => $publisher, 'platforms' => []];
            }

            $groups[$key]['platforms'][] = $platform;
        }

        return array_values($groups);
    }
}
