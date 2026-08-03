<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Publishers;

/**
 * Module Marketing — Phase 3 (issue #1433).
 *
 * Contrat commun implemente par chaque publisher reseau social
 * (LinkedIn, Meta, X/Twitter, ...). L'implementation reelle passe par
 * l'agregateur Ayrshare (voir `AyrshareClient` — pas de SDK officiel par
 * plateforme), mais ce contrat garde la porte ouverte a un futur
 * publisher qui appellerait directement l'API native d'une plateforme
 * sans passer par l'agregateur.
 *
 * `SocialPublishingService` route chaque `SocialPost` vers le(s)
 * publisher(s) correspondant a ses `target_platforms` via
 * `SocialPublisherResolver`.
 */
interface SocialPublisherInterface
{
    /**
     * Cle(s) de plateforme (au sens `target_platforms` de `SocialPost`)
     * gerees par ce publisher, ex: `['linkedin']` ou
     * `['facebook_page', 'facebook_group']` pour un publisher Meta qui
     * couvre plusieurs surfaces Facebook.
     *
     * @return array<int, string>
     */
    public function supportedPlatforms(): array;

    /**
     * Publie le contenu sur les plateformes cibles (sous-ensemble de
     * `supportedPlatforms()`) au nom du profil `$profileKey`.
     *
     * @param  array<int, string>  $platforms  sous-ensemble de supportedPlatforms()
     * @param  array<int, string>  $mediaUrls
     * @return array{id: string, status: string, raw: array<string, mixed>}
     */
    public function publish(
        string $profileKey,
        string $content,
        array $platforms,
        array $mediaUrls = [],
    ): array;
}
