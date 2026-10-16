<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services\Stats;

use App\Modules\Marketing\Infrastructure\Services\AyrshareClient;

/**
 * Marketing OS — statistiques via l'agregateur Ayrshare (#8530).
 *
 * Ayrshare expose `POST /analytics/post` : on lui transmet l'identifiant du
 * post qu'il a lui-meme retourne a la publication, et il renvoie les
 * indicateurs par plateforme. On ne lit QUE des valeurs numeriques
 * reellement presentes dans la reponse : toute cle absente est simplement
 * omise, jamais remplacee par un zero ou une estimation.
 *
 * Plateformes couvertes : celles du socle de publication existant
 * (LinkedIn, Meta — pages/groupes/Instagram/Threads — et X/Twitter).
 * YouTube et TikTok ne sont pas couverts par l'agregateur : le resolver ne
 * les resout pas et le service declare alors la donnee indisponible
 * (issue #8532 pour l'extension des connecteurs).
 */
class AyrshareAnalyticsProvider implements PlatformStatsProviderInterface
{
    /** Indicateurs reconnus, dans l'ordre d'affichage souhaite. */
    private const METRICS = [
        'impressions',
        'views',
        'reach',
        'clicks',
        'likes',
        'comments',
        'shares',
        'engagement',
    ];

    public function __construct(private readonly AyrshareClient $client) {}

    /** @return array<int, string> */
    public function supportedPlatforms(): array
    {
        return ['linkedin', 'facebook_page', 'facebook_group', 'instagram', 'threads', 'twitter'];
    }

    /** @return array<string, int|float>|null */
    public function fetch(string $profileKey, string $externalPostId, string $platform): ?array
    {
        $body = $this->client->fetchPostAnalytics($profileKey, $externalPostId);

        $analytics = $body['analytics'] ?? null;

        if (! is_array($analytics)) {
            return null;
        }

        $entry = $analytics[$platform] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        $stats = [];

        foreach (self::METRICS as $metric) {
            $value = $entry[$metric] ?? null;

            if (is_int($value) || is_float($value)) {
                $stats[$metric] = $value;
            }
        }

        return $stats === [] ? null : $stats;
    }
}
