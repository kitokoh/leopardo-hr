<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Pdf;

/**
 * Issue #5242 — rendu arabe RTL « sans cassure » dans dompdf.
 *
 * dompdf ne fait PAS de shaping contextuel (pas de moteur OpenType/HarfBuzz) :
 * les lettres arabes sont rendues déconnectées (cassure visuelle) et dans
 * l'ordre logique (donc inversées visuellement pour un lecteur RTL).
 *
 * Cette classe applique les deux corrections nécessaires AVANT le rendu :
 *   1. **Shaping contextuel** : chaque lettre arabe est convertie vers sa
 *      forme de présentation (U+FB50–U+FEFF : isolée / initiale / médiane /
 *      finale) en fonction de ses voisines, avec les ligatures lām-alef
 *      (ﻻ ﻷ ﻹ ﻵ). Les lettres non-connectantes (ا د ذ ر ز و ى ة ء آ أ إ ؤ)
 *      n'ont que 2 formes.
 *   2. **Inversion RTL** : chaque run arabe est inversé (ordre visuel RTL
 *      dans un rendu LTR) et l'ordre des runs d'une ligne est inversé
 *      (texte arabe à droite, nombres/latin à gauche) — bidi minimal
 *      suffisant pour le bulletin (étiquettes arabes + valeurs latines).
 *
 * Les textes non-arabes passent inchangés. Police requise : Almarai
 * (OFL), qui embarque les formes de présentation — enregistrée dans
 * `PaySlipPdfGenerator::ensureArabicFonts()`.
 *
 * @see https://www.unicode.org/charts/PDF/UFB50.pdf
 */
final class ArabicPdfText
{
    /**
     * Formes de présentation par lettre de base, ordre :
     * [isolée, finale, initiale, médiane].
     * Les lettres à 2 formes : [isolée, finale].
     *
     * @var array<string, list<string>>
     */
    private const PRESENTATION_FORMS = [
        "\u{0621}" => ["\u{FE80}"],
        "\u{0622}" => ["\u{FE81}", "\u{FE82}"],
        "\u{0623}" => ["\u{FE83}", "\u{FE84}"],
        "\u{0624}" => ["\u{FE85}", "\u{FE86}"],
        "\u{0625}" => ["\u{FE87}", "\u{FE88}"],
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}"],
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        "\u{0629}" => ["\u{FE93}", "\u{FE94}"],
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}"],
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}"],
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}"],
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}"],
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}"],
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}"],
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
    ];

    /**
     * Ligatures lām-alef : [lām + alef] → [isolée, finale].
     *
     * @var array<string, list<string>>
     */
    private const LAM_ALEF_LIGATURES = [
        "\u{0644}\u{0627}" => ["\u{FEFB}", "\u{FEFC}"], // ﻻ
        "\u{0644}\u{0623}" => ["\u{FEF5}", "\u{FEF6}"], // ﻷ
        "\u{0644}\u{0625}" => ["\u{FEF7}", "\u{FEF8}"], // ﻹ
        "\u{0644}\u{0622}" => ["\u{FEF9}", "\u{FEFA}"], // ﻵ
    ];

    /**
     * Met en forme un texte contenant de l'arabe pour un rendu dompdf LTR :
     * formes contextuelles + inversion RTL (mots + ordre des runs).
     */
    public static function shape(string $text): string
    {
        if (! self::containsArabic($text)) {
            return $text;
        }

        $runs = self::splitRuns($text);
        $out = [];
        foreach ($runs as $run) {
            $out[] = self::isArabicRun($run)
                ? self::mbStrrev(self::shapeArabicRun($run))
                : $run;
        }

        // Bidi minimal : l'ordre des runs est inversé (arabe à droite).
        return implode('', array_reverse($out));
    }

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    /**
     * Découpe en runs : séquences maximales de caractères arabes (espaces
     * internes inclus) vs tout le reste. Les espaces internes gardent les
     * mots d'un même groupe ensemble pour l'inversion.
     *
     * @return list<string>
     */
    private static function splitRuns(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $runs = [];
        $current = '';
        $currentIsArabic = null;

        foreach ($chars as $ch) {
            $isArabic = self::isArabicChar($ch);
            $isSpace = $ch === ' ' || $ch === "\u{00A0}";

            // Les espaces suivent le run en cours (jamais un run à part).
            if ($isSpace && $currentIsArabic !== null) {
                $current .= $ch;

                continue;
            }

            if ($currentIsArabic === null || $isArabic === $currentIsArabic) {
                $current .= $ch;
                $currentIsArabic = $isArabic;
            } else {
                $runs[] = $current;
                $current = $ch;
                $currentIsArabic = $isArabic;
            }
        }

        if ($current !== '') {
            $runs[] = $current;
        }

        return $runs;
    }

    private static function isArabicRun(string $run): bool
    {
        return self::containsArabic($run);
    }

    private static function isArabicChar(string $ch): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $ch);
    }

    /**
     * Formes contextuelles d'un run arabe (lettres + espaces éventuels).
     */
    private static function shapeArabicRun(string $run): string
    {
        $chars = preg_split('//u', $run, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($chars);
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];

            if (! isset(self::PRESENTATION_FORMS[$ch])) {
                $out .= $ch;

                continue;
            }

            // Ligature lām-alef (ﻻ ﻷ ﻹ ﻵ) : consomme le alef suivant.
            if ($ch === "\u{0644}" && $i + 1 < $count) {
                $pair = $ch.$chars[$i + 1];
                if (isset(self::LAM_ALEF_LIGATURES[$pair])) {
                    $prevConnects = self::prevConnects($chars, $i);
                    $form = $prevConnects
                        ? self::LAM_ALEF_LIGATURES[$pair][1]   // finale
                        : self::LAM_ALEF_LIGATURES[$pair][0];  // isolée
                    $out .= $form;
                    $i++; // alef consommé

                    continue;
                }
            }

            $forms = self::PRESENTATION_FORMS[$ch];
            $prevConnects = self::prevConnects($chars, $i);
            $nextConnects = self::nextConnects($chars, $i);

            if (count($forms) === 4) {
                if ($prevConnects && $nextConnects) {
                    $out .= $forms[3]; // médiane
                } elseif ($prevConnects) {
                    $out .= $forms[1]; // finale
                } elseif ($nextConnects) {
                    $out .= $forms[2]; // initiale
                } else {
                    $out .= $forms[0]; // isolée
                }
            } else {
                // Lettres à 1 ou 2 formes (ء n'a qu'une forme).
                $out .= $prevConnects && isset($forms[1]) ? $forms[1] : $forms[0];
            }
        }

        return $out;
    }

    /**
     * Le caractère précédent est-il une lettre connectante (qui se lie à la
     * suivante) ? Espace/vide = non.
     *
     * @param  list<string>  $chars
     */
    private static function prevConnects(array $chars, int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $ch = $chars[$j];
            if ($ch === ' ' || $ch === "\u{00A0}" || ! self::isArabicChar($ch)) {
                return false;
            }

            // Lettre à 4 formes = connectante.
            return isset(self::PRESENTATION_FORMS[$ch]) && count(self::PRESENTATION_FORMS[$ch]) === 4;
        }

        return false;
    }

    /**
     * Le caractère suivant est-il une lettre arabe (accepte la liaison depuis
     * la gauche) ? Toute lettre — même à 2 formes (ا د ذ ر ز و ى ة ء…) —
     * fait prendre à la précédente sa forme initiale/médiane.
     *
     * @param  list<string>  $chars
     */
    private static function nextConnects(array $chars, int $i): bool
    {
        for ($j = $i + 1, $count = count($chars); $j < $count; $j++) {
            $ch = $chars[$j];
            if ($ch === ' ' || $ch === "\u{00A0}" || ! self::isArabicChar($ch)) {
                return false;
            }

            return isset(self::PRESENTATION_FORMS[$ch]);
        }

        return false;
    }

    private static function mbStrrev(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_reverse($chars));
    }
}
