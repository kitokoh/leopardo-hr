<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface;

/**
 * Profil de surface publique d'une verticale (BC-41, #9579).
 *
 * ## Ce que ce value object résout
 *
 * La mission demande « un site web spécialisé destiné au grand public » pour
 * chaque verticale. La tentation est de créer un front par verticale
 * (`front/restaurant-web`, `front/pharmacy-web`, …) : douze duplications de
 * l'i18n, du SEO, du consentement et de l'accessibilité, qui divergent en trois
 * mois.
 *
 * Un profil décrit la SPÉCIALISATION sans dupliquer le socle :
 *
 *  - `capabilities` : ce que le visiteur peut FAIRE (commander, réserver,
 *    prendre rendez-vous, demander un devis, présenter une ordonnance) ;
 *  - `blockTypes` : les blocs attendus sur la surface, dans l'ordre de
 *    pertinence pour cette verticale ;
 *  - `theme` : les tokens de rendu par défaut (densité, rayon, typographie) ;
 *  - `primaryCta` : l'action mise en avant.
 *
 * Le front rend le profil. Ajouter une verticale = un provider + ses blocs.
 * Zéro modification de Branding, zéro modification du front.
 *
 * ## Immuabilité
 *
 * Tous les champs sont `readonly` et les listes sont figées à la construction.
 * Un profil est construit une fois au boot et partagé : s'il était mutable,
 * une requête pourrait modifier le profil vu par la suivante.
 */
final class SurfaceProfile
{
    /**
     * @param  list<string>  $capabilities  capacités publiques (allowlist)
     * @param  list<string>  $blockTypes    types de blocs attendus, ordonnés
     * @param  array<string, string|int|float|bool>  $theme  tokens de rendu
     */
    public function __construct(
        public readonly string $vertical,
        public readonly string $label,
        public readonly array $capabilities,
        public readonly array $blockTypes,
        public readonly array $theme,
        public readonly ?string $primaryCta = null,
    ) {
        if ($vertical === '') {
            throw new \InvalidArgumentException('SURFACE_PROFILE_VERTICAL_REQUIRED');
        }

        if ($label === '') {
            throw new \InvalidArgumentException('SURFACE_PROFILE_LABEL_REQUIRED');
        }

        // Une capacité inconnue est une faute de programmation : le front ne
        // saura pas la rendre, et un bouton qui ne mène nulle part est pire
        // qu'un bouton absent. On refuse au boot plutôt qu'à l'écran.
        foreach ($capabilities as $capability) {
            if (!in_array($capability, SurfaceCapability::values(), true)) {
                throw new \InvalidArgumentException(sprintf(
                    'SURFACE_PROFILE_UNKNOWN_CAPABILITY: « %s » (verticale %s).',
                    $capability,
                    $vertical,
                ));
            }
        }

        // Le CTA primaire doit être une capacité réellement déclarée : sinon le
        // front mettrait en avant une action que le profil ne fournit pas.
        if ($primaryCta !== null && ! in_array($primaryCta, $capabilities, true)) {
            throw new \InvalidArgumentException(sprintf(
                'SURFACE_PROFILE_PRIMARY_CTA_NOT_DECLARED: « %s » (verticale %s).',
                $primaryCta,
                $vertical,
            ));
        }
    }

    /**
     * Profil de repli : socle `core.*`, aucune capacité spécialisée.
     *
     * C'est ce que reçoit une Brand dont la verticale n'a pas (encore) livré
     * son profil. Une Brand peut donc être publiée avant sa verticale — la
     * surface s'affiche, simplement sans les parcours spécialisés.
     */
    public static function generic(): self
    {
        return new self(
            vertical: 'generic',
            label: 'Générique',
            capabilities: [SurfaceCapability::Contact->value, SurfaceCapability::Directions->value],
            blockTypes: [
                'core.hero',
                'core.features',
                'core.gallery',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'comfortable',
                'radius' => '0.5rem',
                'font_family' => 'system-ui, sans-serif',
            ],
            primaryCta: SurfaceCapability::Contact->value,
        );
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array{
     *   vertical: string,
     *   label: string,
     *   capabilities: list<string>,
     *   block_types: list<string>,
     *   theme: array<string, string|int|float|bool>,
     *   primary_cta: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'vertical' => $this->vertical,
            'label' => $this->label,
            'capabilities' => $this->capabilities,
            'block_types' => $this->blockTypes,
            'theme' => $this->theme,
            'primary_cta' => $this->primaryCta,
        ];
    }
}
