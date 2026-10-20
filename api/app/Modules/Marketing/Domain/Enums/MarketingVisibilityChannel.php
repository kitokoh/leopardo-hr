<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — canal de visibilite d'un objectif (#8531, brief §H).
 *
 * Les quatre canaux du brief, avec leur sens metier explicite :
 *
 *   - `Seo` : referencement naturel dans les moteurs de recherche ;
 *   - `Sea` : referencement payant (campagnes publicitaires) ;
 *   - `Aeo` : Answer Engine Optimization — reponses directes (extraits,
 *     panneaux de reponse) ;
 *   - `Geo` : Generative Engine Optimization — citation dans les moteurs
 *     generatifs et assistants IA.
 *
 * ATTENTION : `Geo` designe ici la visibilite dans les moteurs GENERATIFS,
 * pas le module `Modules/Geo` du depot (qui est geospatial/PostGIS). L'audit
 * #8528 signale explicitement ce risque de confusion : ne jamais reutiliser
 * le module geospatial pour la visibilite IA.
 */
enum MarketingVisibilityChannel: string
{
    case Seo = 'seo';
    case Sea = 'sea';
    case Aeo = 'aeo';
    case Geo = 'geo';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Seo => 'Referencement naturel (SEO)',
            self::Sea => 'Referencement payant (SEA)',
            self::Aeo => 'Reponses directes (AEO)',
            self::Geo => 'Moteurs generatifs et assistants IA (GEO)',
        };
    }
}
