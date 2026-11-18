<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\PublicSurface;

use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileRegistry;

/**
 * Construit le DTO public d'une Brand (BC-41, #9578).
 *
 * ## Liste blanche, jamais `toArray()`
 *
 * Le rendu public est une surface SANS authentification. Renvoyer le modèle
 * exposerait `company_id`, `is_default`, `suspended_at`, `suspension_reason` —
 * c'est-à-dire l'état interne du tenant. La liste blanche fait aussi qu'un
 * champ ajouté au modèle demain ne fuit pas par défaut.
 *
 * ## Le profil de verticale est résolu, pas stocké
 *
 * Le DTO porte le profil de surface de la verticale de la Brand. Il est résolu
 * à la lecture depuis le registre : si une verticale livre son profil après
 * qu'une Brand a été publiée, la surface se spécialise immédiatement, sans
 * migration ni republication.
 *
 * ## Les capacités sont déclarées, pas promises
 *
 * Le profil déclare ce que la verticale SAIT faire. Le DTO expose cette
 * déclaration telle quelle ; c'est le rendu qui n'affiche que les capacités
 * réellement honorables par l'établissement (PSP actif, tables configurées).
 * Un bouton qui mène à une erreur est pire qu'un bouton absent.
 */
final class PublicBrandPresenter
{
    public function __construct(
        private readonly SurfaceProfileRegistry $profiles,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Brand $brand, string $locale): array
    {
        $profile = $this->profiles->forVertical($brand->vertical->value);
        $palette = $brand->palette();

        return [
            'slug' => $brand->slug,
            'locale' => $locale,
            'direction' => self::direction($locale),
            'locales' => $brand->publishedLocales(),
            'default_locale' => $brand->defaultLocale(),
            'vertical' => $brand->vertical->value,
            'identity' => [
                'display_name' => $brand->displayName(),
                'tagline' => $brand->tagline(),
                'logo_url' => $brand->logoUrl(),
                'favicon_url' => $this->identityString($brand, 'favicon_url'),
                'og_image_url' => $this->identityString($brand, 'og_image_url'),
            ],
            'palette' => $palette->toArray(),
            'surface' => $this->surface($brand),
            'profile' => $profile->toArray(),
            'published_at' => $brand->published_at?->toIso8601String(),
        ];
    }

    /**
     * Surface publique : host, préfixe de chemin, locales.
     *
     * `host` est exposé parce que le front en a besoin pour construire les URL
     * canoniques et les métadonnées Open Graph. Ce n'est pas une donnée
     * interne : c'est l'adresse publique de la Brand.
     *
     * @return array<string, mixed>
     */
    private function surface(Brand $brand): array
    {
        $surface = is_array($brand->surface) ? $brand->surface : [];
        $prefix = $surface['path_prefix'] ?? null;

        return [
            'host' => $brand->host,
            'path_prefix' => is_string($prefix) && $prefix !== '' ? $prefix : null,
            'locales' => $brand->publishedLocales(),
            'default_locale' => $brand->defaultLocale(),
        ];
    }

    private function identityString(Brand $brand, string $key): ?string
    {
        $identity = is_array($brand->identity) ? $brand->identity : [];
        $value = $identity[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Sens d'écriture d'une locale.
     *
     * L'arabe est la seule locale RTL du produit. Le calcul vit ici et non
     * dans le template : un `dir` recopié dans trois vues finit par diverger,
     * et c'est la vue oubliée qui casse la mise en page arabe.
     */
    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * Profil de la verticale, exposé pour les tests et la télémétrie.
     */
    public function profileFor(Brand $brand): SurfaceProfile
    {
        return $this->profiles->forVertical($brand->vertical->value);
    }
}
