<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Palette;

/**
 * Palette de marque d'une Brand (BC-41, #9578).
 *
 * ## Value object immuable, contraste calculé serveur
 *
 * Une palette est un jeu de couleurs FIXÉ à la construction. `on_primary` —
 * la couleur de texte lisible sur `primary` — n'est jamais fournie par le
 * client : elle est CALCULÉE. Un tenant qui choisit un vert clair `#A3E635`
 * obtiendrait sinon des boutons à libellé blanc, soit un contraste de ~1.3:1,
 * très en dessous du minimum WCAG AA (4.5:1). Le serveur est la seule partie
 * qui voit toutes les valeurs écrites (formulaire, API, import) : c'est donc
 * là que la décision doit être prise, une fois, pour toutes les surfaces.
 *
 * ## Pourquoi un value object et pas trois colonnes
 *
 * Les trois couleurs n'ont de sens qu'ensemble : une palette dont `on_primary`
 * ne correspond pas à `primary` est un bug silencieux qui ne se voit qu'à
 * l'écran. En les regroupant, l'invariant « le texte contraste avec son fond »
 * est vrai par construction — il n'existe aucun état intermédiaire où il serait
 * faux.
 *
 * ## Validation
 *
 * Seul `#rrggbb` (6 chiffres hexadécimaux) est accepté. Pas de nom de couleur
 * CSS, pas de `rgb()`, pas de `#rgb` abrégé : ces formes ont plusieurs
 * interprétations selon le contexte et rendraient le calcul de contraste
 * ambigu. Une valeur invalide lève — on ne « corrige » pas silencieusement une
 * couleur en noir, ce qui masquerait une erreur de saisie.
 */
final class BrandPalette
{
    /** Contraste WCAG AA minimal pour du texte normal. */
    public const AA_NORMAL = 4.5;

    /** Contraste WCAG AA minimal pour du texte large (≥ 18.66px gras / 24px). */
    public const AA_LARGE = 3.0;

    /** Texte sombre par défaut (coal du design system). */
    public const DARK_TEXT = '#111827';

    /** Texte clair par défaut. */
    public const LIGHT_TEXT = '#FFFFFF';

    private const HEX_PATTERN = '/^#[0-9a-f]{6}$/';

    private function __construct(
        public readonly string $primary,
        public readonly string $accent,
        public readonly string $surface,
        public readonly string $onPrimary,
    ) {}

    /**
     * Construit une palette validée, avec `on_primary` calculé.
     *
     * @throws \InvalidArgumentException si une couleur n'est pas `#rrggbb`
     */
    public static function of(
        string $primary,
        string $accent,
        string $surface = '#FFFFFF',
    ): self {
        $primary = self::normalize($primary, 'primary');
        $accent = self::normalize($accent, 'accent');
        $surface = self::normalize($surface, 'surface');

        return new self(
            primary: $primary,
            accent: $accent,
            surface: $surface,
            onPrimary: self::contrastingTextFor($primary),
        );
    }

    /**
     * Palette par défaut du produit (tokens de la charte).
     *
     * Utilisée quand un tenant n'a jamais défini de branding : une Brand doit
     * pouvoir exister sans qu'on lui impose de choisir des couleurs avant de
     * publier.
     */
    public static function default(): self
    {
        return self::of('#0F766E', '#0D9488', '#F8FAFC');
    }

    /**
     * Reconstruit une palette depuis un tableau persisté.
     *
     * `on_primary` stocké est IGNORÉ et recalculé : une valeur écrite par une
     * version antérieure du code (ou par un import) ne doit pas pouvoir
     * imposer un contraste insuffisant. Le calcul est la source de vérité, pas
     * la colonne.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $primary = is_string($data['primary'] ?? null) ? $data['primary'] : '#0F766E';
        $accent = is_string($data['accent'] ?? null) ? $data['accent'] : '#0D9488';
        $surface = is_string($data['surface'] ?? null) ? $data['surface'] : '#F8FAFC';

        // Une valeur corrompue en base ne doit pas casser le rendu public :
        // on retombe sur la palette par défaut plutôt que de lever dans le
        // chemin d'une requête anonyme.
        try {
            return self::of($primary, $accent, $surface);
        } catch (\InvalidArgumentException) {
            return self::default();
        }
    }

    /**
     * @return array{primary: string, accent: string, surface: string, on_primary: string}
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'accent' => $this->accent,
            'surface' => $this->surface,
            'on_primary' => $this->onPrimary,
        ];
    }

    /**
     * Le contraste réel entre `on_primary` et `primary`.
     *
     * Exposé pour que les tests expriment l'intention (« on veut AA ») plutôt
     * que de recalculer la formule à côté.
     */
    public function primaryContrastRatio(): float
    {
        return self::contrastRatio($this->onPrimary, $this->primary);
    }

    public function meetsAaOnPrimary(): bool
    {
        return $this->primaryContrastRatio() >= self::AA_NORMAL;
    }

    // ── Calcul WCAG 2.1 ──────────────────────────────────────────────────────

    /**
     * Choisit la couleur de texte qui contraste le mieux avec `$background`.
     *
     * On ne teste pas « clair ou sombre » au feeling : on compare réellement
     * le contraste des deux candidats et on garde le meilleur. C'est ce qui
     * garantit qu'un fond moyen (gris 50 %) reçoit quand même le meilleur des
     * deux, même si aucun n'atteint AA — le rendu reste le moins mauvais
     * possible au lieu d'être arbitraire.
     */
    public static function contrastingTextFor(
        string $background,
        string $dark = self::DARK_TEXT,
        string $light = self::LIGHT_TEXT,
    ): string {
        $bg = self::parse($background);

        if ($bg === null) {
            return $light;
        }

        $darkRatio = self::contrastRatio($dark, $background);
        $lightRatio = self::contrastRatio($light, $background);

        return $darkRatio >= $lightRatio ? $dark : $light;
    }

    /**
     * Ratio de contraste WCAG 2.1 entre deux couleurs.
     *
     * (L1 + 0.05) / (L2 + 0.05) où L est la luminance relative. Le résultat est
     * toujours ≥ 1 et symétrique.
     */
    public static function contrastRatio(string $a, string $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);

        $lighter = max($la, $lb);
        $darker = min($la, $lb);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Luminance relative WCAG 2.1 (sRGB linéarisé).
     *
     * 0.2126 R + 0.7152 G + 0.0722 B sur les composantes linéarisées. Une
     * couleur invalide retourne 0 (noir) : le contraste calculé sera alors
     * celui du noir, ce qui est le comportement le plus conservateur.
     */
    public static function relativeLuminance(string $color): float
    {
        $rgb = self::parse($color);

        if ($rgb === null) {
            return 0.0;
        }

        [$r, $g, $b] = array_map(
            static function (int $channel): float {
                $c = $channel / 255;

                return $c <= 0.03928
                    ? $c / 12.92
                    : (($c + 0.055) / 1.055) ** 2.4;
            },
            $rgb,
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function parse(string $color): ?array
    {
        $normalized = strtolower(trim($color));

        if (! preg_match(self::HEX_PATTERN, $normalized)) {
            return null;
        }

        return [
            (int) hexdec(substr($normalized, 1, 2)),
            (int) hexdec(substr($normalized, 3, 2)),
            (int) hexdec(substr($normalized, 5, 2)),
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function normalize(string $color, string $field): string
    {
        $normalized = strtolower(trim($color));

        if (! preg_match(self::HEX_PATTERN, $normalized)) {
            throw new \InvalidArgumentException(sprintf(
                'BRAND_PALETTE_INVALID_COLOR: %s doit être au format #rrggbb (reçu « %s »).',
                $field,
                $color,
            ));
        }

        return $normalized;
    }
}
