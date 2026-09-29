<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Payroll;

use Illuminate\Support\Carbon;

/**
 * Contrat partagé « calendrier des jours fériés » (BC-04 PAYROLL).
 *
 * Permet aux modules métier (Planning, congés…) de compter les jours ouvrés
 * et de lire les fériés légaux SANS import croisé
 * `Modules/X -> Modules/Payroll` (règle d'isolation #5584, chantier
 * BOS-023 #8211) — ils ne dépendent que de ce contrat, implémenté par
 * `Payroll\Infrastructure\Services\PublicHolidayCalendarAdapter`.
 *
 * La table `public_holidays` reste la propriété du module Payroll :
 * - `company_id = null`  → férié national (lu par tous les tenants du pays) ;
 * - `company_id != null` → férié d'entreprise (override : pont, fermeture).
 */
interface PublicHolidayCalendar
{
    /**
     * Jours ouvrés réels entre deux dates (inclusives), hors jours de repos
     * hebdomadaire et fériés du pays (avec override entreprise).
     *
     * Comportement du service canonique `PublicHolidayService` préservé :
     * fallback « calendrier hors jours de repos » quand aucun férié n'est
     * configuré pour le pays.
     *
     * @param  Carbon  $start  début inclus
     * @param  Carbon  $end  fin inclusive
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

    /**
     * Fériés nationaux fixes d'un pays pour une année — lecture brute de la
     * table (lignes `company_id IS NULL`, année exacte OU récurrentes), SANS
     * fusion du calendrier islamique ni cache.
     *
     * L'expansion des fériés récurrents sur l'année demandée (#1936) reste
     * à la charge du consommateur (calendrier des congés).
     *
     * @return array<int, array{date: string, name: string, holiday_type: string, is_recurring: bool, month_day: string|null}>
     */
    public function nationalHolidays(string $countryCode, int $year): array;
}
