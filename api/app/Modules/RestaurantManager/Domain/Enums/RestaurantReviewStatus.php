<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut de modération d'un avis client public (RESTO-902, issue #7747).
 *
 * Un avis soumis depuis la page publique naît `pending` ; seul un gérant le
 * publie (`published`, visible dans `GET /public/restaurants/{slug}/reviews`
 * et dans la note moyenne) ou le rejette (`rejected`, jamais exposé).
 */
enum RestaurantReviewStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';

    /**
     * Valeurs autorisées (validation FormRequest / filtre modération).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::PUBLISHED => 'Publié',
            self::REJECTED => 'Rejeté',
        };
    }
}
