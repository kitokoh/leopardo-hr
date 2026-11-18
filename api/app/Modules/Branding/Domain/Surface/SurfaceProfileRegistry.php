<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Exceptions\DuplicateSurfaceProfileException;

/**
 * Registre des profils de surface, un par verticale (BC-41, #9579).
 *
 * ## Invariants
 *
 * 1. **Une verticale = un profil.** Deux providers qui déclarent `restaurant`
 *    sont une erreur de configuration qui doit être vue au boot, pas un
 *    `array_merge` silencieux où le dernier gagne. On lève à l'enregistrement.
 * 2. **Un provider ne sort pas du registre fermé.** `vertical()` doit être un
 *    cas de `BrandVertical` hors `generic` — sinon la Brand de cette verticale
 *    n'aurait aucun profil résolvable.
 * 3. **Le registre est figé après construction.** `all()` renvoie une copie ;
 *    aucun appelant ne peut injecter un profil à la volée depuis une requête.
 * 4. **`generic` est toujours présent.** C'est le repli : une Brand dont la
 *    verticale n'a pas de provider doit quand même obtenir une surface.
 *
 * Le registre ne fait **aucune E/S** : il est construit une fois par le service
 * provider du module, à partir du tag de conteneur. C'est ce qui le rend
 * testable sans base ni HTTP.
 */
final class SurfaceProfileRegistry
{
    /** @var array<string, SurfaceProfile> */
    private array $profiles = [];

    /**
     * @param  iterable<SurfaceProfileProvider>  $providers
     */
    public static function build(iterable $providers): self
    {
        $registry = new self;

        // Le repli est enregistré en premier : un provider ne peut pas le
        // remplacer (il est refusé par `register()`), mais il doit exister
        // même si aucun provider n'est installé.
        $registry->profiles[BrandVertical::Generic->value] = SurfaceProfile::generic();

        foreach ($providers as $provider) {
            $registry->register($provider->profile());
        }

        return $registry;
    }

    /**
     * Enregistre un profil.
     *
     * @throws DuplicateSurfaceProfileException
     * @throws \InvalidArgumentException si la verticale est inconnue ou `generic`
     */
    public function register(SurfaceProfile $profile): void
    {
        $vertical = $profile->vertical;

        if ($vertical === BrandVertical::Generic->value) {
            throw new \InvalidArgumentException(
                'SURFACE_PROFILE_GENERIC_IS_RESERVED: le profil « generic » est fourni par le socle et ne peut pas être remplacé.'
            );
        }

        if (! in_array($vertical, BrandVertical::specialised(), true)) {
            throw new \InvalidArgumentException(sprintf(
                'SURFACE_PROFILE_UNKNOWN_VERTICAL: « %s » n\'est pas une verticale du registre BrandVertical.',
                $vertical,
            ));
        }

        if (isset($this->profiles[$vertical])) {
            throw DuplicateSurfaceProfileException::forVertical($vertical);
        }

        $this->profiles[$vertical] = $profile;
    }

    /**
     * Profil d'une verticale, avec repli sur `generic`.
     *
     * Le repli est délibéré et non silencieux : l'appelant peut distinguer les
     * deux cas via `has()` s'il veut signaler qu'une verticale n'a pas livré
     * son profil (télémétrie produit).
     */
    public function forVertical(string $vertical): SurfaceProfile
    {
        return $this->profiles[$vertical] ?? $this->profiles[BrandVertical::Generic->value];
    }

    public function has(string $vertical): bool
    {
        return isset($this->profiles[$vertical]);
    }

    /**
     * @return array<string, SurfaceProfile>
     */
    public function all(): array
    {
        return $this->profiles;
    }

    /**
     * Verticales qui ont un profil dédié (hors `generic`).
     *
     * @return list<string>
     */
    public function specialisedVerticals(): array
    {
        return array_values(array_filter(
            array_keys($this->profiles),
            static fn (string $vertical): bool => $vertical !== BrandVertical::Generic->value,
        ));
    }
}
