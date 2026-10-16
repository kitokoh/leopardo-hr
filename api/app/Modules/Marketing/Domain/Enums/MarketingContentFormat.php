<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Enums;

/**
 * Marketing OS — format de contenu (brief §B).
 */
enum MarketingContentFormat: string
{
    case VideoShort = 'video_short';
    case VideoLong = 'video_long';
    case Image = 'image';
    case Carousel = 'carousel';
    case Article = 'article';
    case Ad = 'ad';
    case Testimonial = 'testimonial';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::VideoShort => 'Vidéo courte',
            self::VideoLong => 'Vidéo longue',
            self::Image => 'Image',
            self::Carousel => 'Carrousel',
            self::Article => 'Article',
            self::Ad => 'Publicité',
            self::Testimonial => 'Témoignage',
            self::Other => 'Autre',
        };
    }

    public function isVideo(): bool
    {
        return $this === self::VideoShort || $this === self::VideoLong;
    }
}
