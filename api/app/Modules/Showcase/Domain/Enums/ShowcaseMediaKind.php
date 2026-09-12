<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Enums;

/**
 * Types de médias d'une vitrine (BC-27 SHOWCASE, V-MEDIA #6872).
 *
 * - `logo` : identité visuelle du tenant affichée par le thème. Types
 *   autorisés : PNG, JPEG, WebP et SVG (le SVG n'est toléré que pour le
 *   logo, jamais pour une image de contenu) ;
 * - `section` : image rattachée à une section publique par identifiant
 *   stable. Types autorisés : PNG, JPEG, WebP.
 *
 * Aucun redimensionnement/format de variante n'est produit : aucune
 * extension image (GD/Imagick) ni librairie tierce n'est présente dans
 * `api/composer.json`. La limite de poids par type est donc la garantie de
 * maîtrise du stockage (et le WebP est accepté tel quel à l'upload).
 */
enum ShowcaseMediaKind: string
{
    case Logo = 'logo';
    case Section = 'section';

    /**
     * Valeurs exposées par l'API (validation `kind`).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    /**
     * Extensions autorisées pour ce type (minuscules, sans point).
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Logo => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            self::Section => ['png', 'jpg', 'jpeg', 'webp'],
        };
    }

    /**
     * Poids maximum accepté (kilooctets) — documenté côté éditeur admin.
     */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Logo => 2048,
            self::Section => 5120,
        };
    }

    public function allowsSvg(): bool
    {
        return $this === self::Logo;
    }

    public static function tryFromOrNull(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
