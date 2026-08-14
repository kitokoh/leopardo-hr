<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1811 — Jours fériés fixes 2024–2027 pour DZ, CM, CI, SN.
 * Les fériés islamiques (Aïd, Maouloud…) sont à saisir depuis l'admin
 * (issue #1812 pour le calcul automatique) — non inclus ici.
 *
 * Idempotent : ne réinsère pas si des lignes existent déjà pour le pays.
 */
class PublicHolidaySeeder extends Seeder
{
    private const FIXED_HOLIDAYS = [
        // DZ — Algérie (fixes)
        'DZ' => [['01-01', 'Jour de l\'an'], ['05-01', 'Fête du Travail'], ['07-05', 'Fête de l\'Indépendance'], ['11-01', 'Fête de la Révolution']],
        // CM — Cameroun (fixes)
        'CM' => [['01-01', 'Jour de l\'an'], ['02-11', 'Fête nationale (Youth Day)'], ['05-01', 'Fête du Travail'], ['05-20', 'Fête nationale'], ['08-15', 'Assomption'], ['12-25', 'Noël']],
        // CI — Côte d'Ivoire (fixes)
        'CI' => [['01-01', 'Jour de l\'an'], ['05-01', 'Fête du Travail'], ['08-07', 'Fête de l\'Indépendance'], ['08-15', 'Assomption'], ['11-01', 'Toussaint'], ['11-15', 'Fête nationale de la Paix'], ['12-25', 'Noël']],
        // SN — Sénégal (fixes)
        'SN' => [['01-01', 'Jour de l\'an'], ['04-04', 'Fête de l\'Indépendance'], ['05-01', 'Fête du Travail'], ['08-15', 'Assomption'], ['11-01', 'Toussaint'], ['12-25', 'Noël']],
    ];

    public function run(): void
    {
        foreach (self::FIXED_HOLIDAYS as $countryCode => $holidays) {
            if (PublicHoliday::query()->where('country_code', $countryCode)->exists()) {
                continue; // déjà seedé (idempotent)
            }

            $rows = [];
            foreach (range(2024, 2027) as $year) {
                foreach ($holidays as [$monthDay, $name]) {
                    $rows[] = [
                        'company_id' => null,
                        'country_code' => $countryCode,
                        'name' => $name,
                        'date' => sprintf('%d-%s', $year, $monthDay),
                        'year' => $year,
                        'is_recurring' => true,
                        'month_day' => $monthDay,
                        'holiday_type' => 'fixed',
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('public_holidays')->insert($rows);
                $this->command?->info(sprintf('PublicHolidaySeeder : %d fériés fixes %s insérés.', count($rows), $countryCode));
            }
        }
    }
}
