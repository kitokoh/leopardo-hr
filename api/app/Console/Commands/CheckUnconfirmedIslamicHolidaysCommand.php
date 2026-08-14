<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Payroll\Infrastructure\Services\IslamicCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Issue #1812 — Rappel annuel des fêtes islamiques non confirmées.
 *
 * Lancé en novembre pour l'année suivante : si des dates islamiques restent
 * approximatives (`confirmed = false`), un warning structuré est journalisé
 * et la liste est affichée en sortie pour les opérations / dashboards.
 *
 * Le canal de notification (email/push plateforme) sera branché ici quand un
 * canal super-admin existera (voir CommunicationService) — le contrat reste
 * le même : « X fêtes non confirmées pour YYYY ».
 */
class CheckUnconfirmedIslamicHolidaysCommand extends Command
{
    protected $signature = 'islamic:check-unconfirmed {--year= : Année cible (défaut : année suivante)}';

    protected $description = 'Vérifie les dates des fêtes islamiques non confirmées pour une année (rappel admin).';

    public function handle(IslamicCalendarService $calendar): int
    {
        $year = (int) ($this->option('year') ?: (now()->year + 1));

        $unconfirmed = $calendar->unconfirmedForYear($year);

        if ($unconfirmed === []) {
            $this->info(sprintf('Toutes les fêtes islamiques de %d sont confirmées.', $year));

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d fête(s) islamique(s) non confirmée(s) pour %d :', count($unconfirmed), $year));
        foreach ($unconfirmed as $holiday) {
            $this->line(sprintf('  - %s (%s)', $holiday['holiday_key'], $holiday['gregorian_date']));
        }

        Log::warning('islamic-calendar.unconfirmed-holidays', [
            'year' => $year,
            'count' => count($unconfirmed),
            'holidays' => $unconfirmed,
        ]);

        return self::SUCCESS;
    }
}
