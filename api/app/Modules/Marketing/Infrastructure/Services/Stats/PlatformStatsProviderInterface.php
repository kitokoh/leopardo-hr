<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Stats;

/**
 * Marketing OS — contrat de recuperation des statistiques d'une publication
 * aupres d'une plateforme (#8530).
 *
 * Chaque implementation declare les plateformes qu'elle sait interroger et
 * renvoie les indicateurs REELLEMENT fournis par l'API. Le contrat autorise
 * explicitement le retour `null` : c'est le cas normal d'une plateforme qui
 * n'expose pas la donnee demandee. Aucune implementation ne doit fabriquer
 * une valeur de repli — l'absence de donnee est une information en soi.
 */
interface PlatformStatsProviderInterface
{
    /**
     * Cles de plateforme (au sens `marketing_publications.platform`) gerees
     * par ce provider.
     *
     * @return array<int, string>
     */
    public function supportedPlatforms(): array;

    /**
     * Interroge l'API et retourne les indicateurs disponibles.
     *
     * @return array<string, int|float>|null `null` = la plateforme ne fournit
     *                                       pas la donnee (jamais une estimation)
     *
     * @throws \RuntimeException en cas d'echec technique de l'appel API
     */
    public function fetch(string $profileKey, string $externalPostId, string $platform): ?array;
}
