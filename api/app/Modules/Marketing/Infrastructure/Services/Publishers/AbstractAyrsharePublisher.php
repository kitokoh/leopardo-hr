<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

use App\Modules\Marketing\Infrastructure\Services\AyrshareClient;

/**
 * Base commune aux publishers reseaux sociaux (issue #1433). Chaque
 * plateforme n'a pas de SDK/API native distincte cote Leopardo : toutes
 * passent par l'agregateur Ayrshare (`AyrshareClient`), donc cette classe
 * se contente de deleguer l'appel HTTP et de laisser chaque sous-classe
 * declarer les cles `target_platforms` qu'elle gere.
 *
 * Si une plateforme necessitait un jour un appel API natif dedie (hors
 * Ayrshare), il suffirait de faire un nouveau `SocialPublisherInterface`
 * qui n'etend pas cette classe.
 */
abstract class AbstractAyrsharePublisher implements SocialPublisherInterface
{
    public function __construct(
        protected readonly AyrshareClient $ayrshareClient,
    ) {}

    /**
     * @param  array<int, string>  $platforms
     * @param  array<int, string>  $mediaUrls
     * @return array{id: string, status: string, raw: array<string, mixed>}
     */
    public function publish(
        string $profileKey,
        string $content,
        array $platforms,
        array $mediaUrls = [],
    ): array {
        return $this->ayrshareClient->publishPost(
            profileKey: $profileKey,
            content: $content,
            platforms: $platforms,
            mediaUrls: $mediaUrls,
        );
    }
}
