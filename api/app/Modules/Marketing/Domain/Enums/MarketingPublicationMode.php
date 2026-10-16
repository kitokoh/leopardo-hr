<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — mode de publication (brief §F), distinction CRITIQUE.
 *
 *  - `Direct`   : publication réellement exécutée par une API / un
 *                 agrégateur configuré (Ayrshare pour LinkedIn/Meta/X).
 *  - `Assisted` : Leopardo prépare et ouvre tout, mais la publication est
 *                 réalisée par un humain sur la plateforme. Le passage à
 *                 `published` exige une CONFIRMATION explicite de
 *                 l'utilisateur + l'URL réelle.
 *
 * Aucun contenu n'est jamais marqué `published` parce qu'une page de
 * réseau social a simplement été ouverte.
 */
enum MarketingPublicationMode: string
{
    case Direct = 'direct';
    case Assisted = 'assisted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Publication directe (API)',
            self::Assisted => 'Publication assistée (manuelle)',
        };
    }
}
