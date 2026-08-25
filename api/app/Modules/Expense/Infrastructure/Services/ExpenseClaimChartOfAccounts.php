<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Services;

/**
 * #5235 — Plan de comptes des notes de frais (par catégorie d'item).
 *
 * Registre immuable des comptes utilisés pour les écritures comptables d'une
 * `ExpenseClaim` approuvée, par catégorie d'item (PCG — plan « famille PCG »
 * utilisé tel quel par les pays francophones et la zone OHADA, cf.
 * `PayrollCountryChartOfAccounts` #5256).
 *
 * Modèle d'écriture (partie double, docs/architecture/COMPTABILITE_CONCEPTION.md
 * §6.2 — lignes « Expense → écritures comptables ») :
 *   - débit  : compte de charge selon la catégorie dominante de la note ;
 *   - crédit : 425 « Personnel — avances et acomptes » (remboursement de
 *     frais dû à l'employé — équivalent du C 425 du flux paie #5239).
 *
 * Niveau de confiance (constitution §III) :
 *   - 'pilot' : codes de pratique courante (PCG/PCN/SYSCOHADA classe 6/4),
 *     à valider par un expert-comptable local avant généralisation.
 */
final class ExpenseClaimChartOfAccounts
{
    /**
     * @var array<string, array{code: string, label: string}>
     */
    private const CATEGORY_ACCOUNTS = [
        'transport' => ['code' => '6251', 'label' => 'Voyages et déplacements'],
        'meals' => ['code' => '6256', 'label' => 'Missions (repas)'],
        'accommodation' => ['code' => '6256', 'label' => 'Missions (hébergement)'],
        'office' => ['code' => '6064', 'label' => 'Fournitures administratives'],
        'communication' => ['code' => '626', 'label' => 'Frais postaux et de télécommunications'],
        'other' => ['code' => '658', 'label' => 'Charges diverses de gestion courante'],
    ];

    /**
     * Compte créditeur (contrepartie) du remboursement de frais à l'employé.
     *
     * @return array{code: string, label: string}
     */
    public static function counterpartAccount(): array
    {
        return ['code' => '425', 'label' => 'Personnel — avances et acomptes (remboursement de frais)'];
    }

    /**
     * Compte de charge pour une catégorie d'item de note de frais.
     *
     * @return array{code: string, label: string}
     */
    public static function forCategory(string $category): array
    {
        return self::CATEGORY_ACCOUNTS[$category] ?? self::CATEGORY_ACCOUNTS['other'];
    }
}
