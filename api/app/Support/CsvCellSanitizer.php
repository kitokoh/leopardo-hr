<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CsvCellSanitizer — garde OWASP contre l'injection de formules CSV.
 *
 * Excel/LibreOffice interprètent comme formule toute cellule commençant par
 * `=`, `+`, `-`, `@`, tabulation ou retour-chariot. Une valeur contrôlée par
 * l'utilisateur (nom, note RH, contenu d'audit) commençant par l'un de ces
 * préfixes devient un vecteur d'exfiltration à l'ouverture du fichier.
 *
 * Stratégie (issue #4169, racine #2223) : préfixer la cellule par une
 * apostrophe `'` — Excel affiche alors le contenu littéral. Les valeurs
 * NUMÉRIQUES (y compris les montants négatifs comme `-1234.5`) ne sont jamais
 * préfixées : Excel les parse comme littéraux numériques, pas comme formules.
 *
 * Usage : sur TOUT point d'export CSV d'un champ texte non fiable, avant
 * l'échappement structurel (doublage des `"`, quotes autour de `,`/`"`/newline).
 */
final class CsvCellSanitizer
{
    /** Préfixes de formule OWASP (ASCII) : = + - @ TAB CR. */
    private const FORMULA_PREFIXES = "=+-@\t\r";

    /**
     * Neutralise une cellule texte contre l'injection de formule.
     *
     * @param  mixed  $value  valeur brute (string, int, float, null…)
     * @return string cellule sûre à écrire dans le CSV
     */
    public static function neutralize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $cell = (string) $value;

        if ($cell === '' || is_numeric($cell)) {
            return $cell;
        }

        if (str_contains(self::FORMULA_PREFIXES, $cell[0])) {
            return "'".$cell;
        }

        return $cell;
    }
}
