<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Support;

/**
 * Choisit la couleur de TEXTE lisible sur un fond arbitraire.
 *
 * Pourquoi côté serveur : `primary_color` est libre (n'importe quel `#rrggbb`
 * est accepté par la validation). Un tenant qui choisit un vert clair
 * `#A3E635` obtenait jusqu'ici des boutons à libellé BLANC — un contraste de
 * ~1.3:1, très en dessous du minimum WCAG AA (4.5:1). Le serveur est la seule
 * partie qui voit toutes les valeurs écrites (formulaire, API, import), donc
 * c'est là que la décision doit être prise : le client ne peut pas choisir une
 * combinaison illisible, il la reçoit déjà corrigée.
 *
 * Formule : luminance relative WCAG 2.1 (sRGB linéarisé, puis 0.2126 R +
 * 0.7152 G + 0.0722 B). On ne teste pas « clair ou sombre » au feeling : on
 * compare réellement le contraste des deux candidats et on garde le meilleur.
 */
final class ContrastingTextColor
{
    /** Texte sombre par défaut (coal, utilisé par le design system). */
    public const DARK = '#111827';

    /** Texte clair par défaut. */
    public const LIGHT = '#FFFFFF';

    /**
     * Contraste WCAG AA minimal pour du texte normal.
     *
     * Exposé pour que les tests expriment l'intention (« on veut AA ») plutôt
     * qu'un nombre magique.
     */
    public const AA_NORMAL = 4.5;

    /**
     * Retourne `$dark` ou `$light` selon celui qui contraste le plus avec
     * `$background`.
     *
     * Une valeur de fond invalide retombe sur le texte clair : on préfère le
     * comportement historique plutôt que de lever une exception dans le chemin
     * de rendu d'un payload.
     */
    public static function for(string $background, string $dark = self::DARK, string $light = self::LIGHT): string
    {
        $rgb = self::parse($background);

        if ($rgb === null) {
            return $light;
        }

        [$r, $g, $b] = $rgb;
        $backgroundLuminance = self::luminance($r, $g, $b);

        $darkRgb = self::parse($dark);
        $lightRgb = self::parse($light);

        if ($darkRgb === null || $lightRgb === null) {
            return $light;
        }

        $darkContrast = self::ratio($backgroundLuminance, self::luminance(...$darkRgb));
        $lightContrast = self::ratio($backgroundLuminance, self::luminance(...$lightRgb));

        // Égalité stricte → texte sombre : c'est le choix esthétique du design
        // system pour les fonds moyens, et il évite un blanc « qui bave ».
        return $darkContrast >= $lightContrast ? $dark : $light;
    }

    /**
     * Contraste WCAG entre deux couleurs (1:1 à 21:1).
     *
     * Utile aux tests et au diagnostic : on peut vérifier qu'une paire
     * réellement configurée atteint AA, pas seulement qu'elle « a l'air ».
     */
    public static function ratioBetween(string $first, string $second): float
    {
        $a = self::parse($first);
        $b = self::parse($second);

        if ($a === null || $b === null) {
            return 1.0;
        }

        return self::ratio(self::luminance(...$a), self::luminance(...$b));
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null Composantes 0..1
     */
    private static function parse(string $hex): ?array
    {
        $value = ltrim(trim($hex), '#');

        if (strlen($value) === 3) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (strlen($value) !== 6 || ! ctype_xdigit($value)) {
            return null;
        }

        return [
            hexdec(substr($value, 0, 2)) / 255,
            hexdec(substr($value, 2, 2)) / 255,
            hexdec(substr($value, 4, 2)) / 255,
        ];
    }

    /**
     * Luminance relative WCAG 2.1 (canal linéarisé).
     */
    private static function luminance(float $r, float $g, float $b): float
    {
        return 0.2126 * self::linearize($r)
            + 0.7152 * self::linearize($g)
            + 0.0722 * self::linearize($b);
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function ratio(float $first, float $second): float
    {
        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
