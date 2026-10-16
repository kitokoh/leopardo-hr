<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

/**
 * Marketing OS — capacites de publication DIRECTE par plateforme (#8532).
 *
 * Le brief §F interdit de « pretendre qu'une publication est automatisee si
 * elle necessite encore une intervention manuelle ». Cette classe est la
 * source de verite unique de cette affirmation : elle declare, plateforme par
 * plateforme, si un connecteur de publication directe existe REELLEMENT, et
 * sinon POURQUOI — afin que l'interface puisse l'expliquer a l'operateur au
 * lieu d'echouer silencieusement.
 *
 * Etat reel du socle (audit #8528) : la publication directe passe par
 * l'agregateur existant, qui couvre LinkedIn, Meta (pages, groupes,
 * Instagram, Threads) et X/Twitter. YouTube et TikTok ne sont PAS couverts :
 * ils restent en mode ASSISTE, et cette classe le dit explicitement.
 *
 * Aucun mot de passe de reseau social n'est stocke : l'autorisation passe par
 * la Profile Key de l'agregateur, conservee chiffree
 * (`social_accounts.provider_profile_ref`, cast `encrypted`).
 */
class PlatformPublishingCapability
{
    /**
     * Plateformes dont la publication directe est operationnelle via
     * l'agregateur existant.
     *
     * @var array<int, string>
     */
    public const DIRECT_PLATFORMS = [
        'linkedin',
        'facebook_page',
        'facebook_group',
        'instagram',
        'threads',
        'twitter',
    ];

    /**
     * Plateformes connues du produit mais SANS connecteur de publication
     * directe : le mode assiste s'applique, et l'API l'explique.
     *
     * @var array<string, string>
     */
    public const ASSISTED_ONLY_REASONS = [
        'youtube' => 'Aucun connecteur YouTube n est configure : la publication reste assistee (preparation du titre, de la description et des tags, puis mise en ligne par l operateur).',
        'tiktok' => 'Aucun connecteur TikTok n est configure : la publication reste assistee (preparation de la legende et des hashtags, puis mise en ligne par l operateur).',
    ];

    /** Un connecteur de publication directe existe-t-il pour cette plateforme ? */
    public function supportsDirect(string $platform): bool
    {
        return in_array($platform, self::DIRECT_PLATFORMS, true);
    }

    /**
     * Motif pour lequel la publication directe n'est pas disponible.
     *
     * `null` quand elle l'est : l'appelant n'a alors rien a expliquer.
     */
    public function assistedReason(string $platform): ?string
    {
        if ($this->supportsDirect($platform)) {
            return null;
        }

        return self::ASSISTED_ONLY_REASONS[$platform]
            ?? "Aucun connecteur n est configure pour la plateforme '{$platform}' : la publication reste assistee.";
    }

    /**
     * Matrice des plateformes et de leurs capacites, pour l'ecran de
     * preparation des publications.
     *
     * @return array<int, array{platform: string, direct: bool, reason: string|null}>
     */
    public function matrix(): array
    {
        $rows = [];

        foreach (self::DIRECT_PLATFORMS as $platform) {
            $rows[] = ['platform' => $platform, 'direct' => true, 'reason' => null];
        }

        foreach (self::ASSISTED_ONLY_REASONS as $platform => $reason) {
            $rows[] = ['platform' => $platform, 'direct' => false, 'reason' => $reason];
        }

        return $rows;
    }
}
