<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

/**
 * Issue #5257 — i18n paie ×4 : localisation des NOMS DE LIGNES de bulletin au
 * rendu (API + PDF), sans toucher au moteur ni aux données persistées
 * (pay_slip_lines.name reste le libellé brut d'origine, clé d'audit stable).
 *
 * Les lignes sont créées par PayrollCalculator avec des libellés français
 * figés ('Salaire de base', 'Heures supplémentaires', ...). Cette classe
 * mappe le libellé brut → clé `payroll.line_*` (catalogues ×4) ; tout libellé
 * inconnu (ex. composants personnalisés, libellés pays spécifiques comme
 * « Contribution Nationale (CN) » pour CI) retombe sur le libellé brut.
 */
class PayrollLineLabels
{
    /**
     * Libellés connus du moteur → clés du catalogue `payroll.line_*`.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'Salaire de base' => 'line_base_salary',
        'Heures supplémentaires' => 'line_overtime',
        'Indemnité de congés payés' => 'line_paid_leave_indemnity',
        '13ème mois' => 'line_thirteenth_month',
        'Allocations familiales' => 'line_family_allowance',
        'Cotisations salariales' => 'line_social_contributions',
        'Impot sur le revenu' => 'line_income_tax',
        'Cotisations patronales' => 'line_employer_contributions',
        'Taxe de minimum fiscal' => 'line_flat_tax',
    ];

    /**
     * Traduit un libellé de ligne dans la locale courante (fallback : libellé
     * brut — composants personnalisés et libellés pays non mappés).
     */
    public static function label(string $rawName): string
    {
        $key = self::MAP[$rawName] ?? null;

        if ($key === null) {
            return $rawName;
        }

        $translated = __("payroll.{$key}");

        // Garde : si la clé manque d'un catalogue (parité cassée), ne jamais
        // retourner la clé brute — repli sur le libellé d'origine.
        return $translated === "payroll.{$key}" ? $rawName : $translated;
    }
}
