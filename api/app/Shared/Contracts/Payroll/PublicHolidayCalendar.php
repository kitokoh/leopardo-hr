<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Payroll;

use Illuminate\Support\Carbon;

/**
 * Contrat partagé « calendrier des jours fériés » (fourni par BC-07 PAYROLL).
 *
 * Permet au module Planning (BC-05) de calculer les jours ouvrés des absences
 * et le calendrier des fériés LÉGAUX sans import croisé
 * `Modules/Planning -> Modules/Payroll` (règle d'isolation #5584, cycle
 * WORKFORCE↔PAYROLL résorbé côté Planning — issue #8211 / BOS-023) : le
 * consommateur ne dépend que de ce contrat, implémenté par
 * `Payroll\Infrastructure\Services\SharedPublicHolidayCalendar` et bindé par
 * `PayrollServiceProvider` (pattern `Shared\Contracts\Crm`).
 *
 * Surface volontairement MINIMALE : lecture seule, aucune écriture, aucun
 * modèle Eloquent Payroll ne transite (lignes en tableaux purs).
 */
interface PublicHolidayCalendar
{
    /**
     * Lignes brutes des fériés NATIONAUX (`company_id IS NULL`) d'un pays
     * pour une année : fériés de l'année demandée + fériés récurrents
     * (appliqués à toutes les années, #1936). `date` est la date STOCKÉE
     * (première occurrence pour un récurrent) — le calcul de la date
     * effective d'un récurrent reste à la charge du consommateur (logique
     * éprouvée par les golden tests Planning).
     *
     * @return array<int, array{date: string, name: string, holiday_type: string, is_recurring: bool, month_day: string|null}>
     */
    public function nationalHolidayRows(string $countryCode, int $year): array;

    /**
     * Nombre de jours ouvrés réels entre deux dates (inclusives) : jours
     * calendaires moins les jours de repos hebdomadaire du pays moins les
     * fériés (nationaux + overrides entreprise), arrondi à 2 décimales.
     * Fallback historique : aucun férié configuré pour le pays/année →
     * calendrier hors jours de repos (≈ 22 jours ouvrés mensuels).
     *
     * @param  array<int, array{date: string, name: string, holiday_type: string, company_id: string|int|null}>|null  $holidays  liste préchargée (optionnel)
     * @param  array<int, int>  $restDays  jours de repos ISO (1=lundi..7=dimanche)
     */
    public function workingDaysBetween(
        Carbon $start,
        Carbon $end,
        string $countryCode,
        ?array $holidays = null,
        ?string $companyId = null,
        array $restDays = [6, 7],
    ): float;
}
