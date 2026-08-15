<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1811/#2255 — Jours fériés fixes 2024–2027 pour DZ, CM, CI, SN,
 * FR, MA, TN, TR, CA, ML, GA, CG.
 * Les fériés mobiles (Pâques/Ascension/Pentecôte ; Aïd, Maouloud…) sont à
 * saisir depuis l'admin ou via le calendrier islamique (issue #1812 pour le
 * calcul automatique) — non inclus ici. BF volontairement absent : réforme
 * légale 2026 (loi portant fêtes légales) — texte officiel complet requis.
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
        // FR — France (Code du travail art. L3133-1 ; fixes uniquement —
        // lundi de Pâques, Ascension, lundi de Pentecôte = mobiles, non seedés)
        'FR' => [['01-01', 'Jour de l\'an'], ['05-01', 'Fête du Travail'], ['05-08', 'Victoire 1945'], ['07-14', 'Fête nationale'], ['08-15', 'Assomption'], ['11-01', 'Toussaint'], ['11-11', 'Armistice 1918'], ['12-25', 'Noël']],
        // MA — Maroc (décrets royaux ; Aïd el-Fitr, Aïd el-Adha, 1er Moharrem,
        // Aïd el-Mawlid = mobiles islamiques → IslamicCalendarService #1812)
        'MA' => [['01-01', 'Nouvel An'], ['01-11', 'Manifeste de l\'Indépendance'], ['05-01', 'Fête du Travail'], ['07-30', 'Fête du Trône'], ['08-14', 'Allégeance Oued Eddahab'], ['08-20', 'Révolution du Roi et du Peuple'], ['08-21', 'Fête de la Jeunesse'], ['11-06', 'Marche Verte'], ['11-18', 'Fête de l\'Indépendance']],
        // TN — Tunisie (décrets ; Aïds mobiles islamiques → #1812)
        'TN' => [['01-01', 'Nouvel An'], ['01-14', 'Fête de la Révolution 2011'], ['03-20', 'Fête de l\'Indépendance'], ['04-09', 'Journée des Martyrs'], ['05-01', 'Fête du Travail'], ['07-25', 'Fête de la République'], ['08-13', 'Fête de la Femme'], ['10-15', 'Fête de l\'Évacuation'], ['12-17', 'Fête de la Révolution 2010-2011']],
        // TR — Turquie (Ulusal Bayram ve Genel Tatiller Kanunu ; Ramazan/Kurban
        // Bayramı mobiles → #1812)
        'TR' => [['01-01', 'Yılbaşı (Nouvel An)'], ['04-23', 'Ulusal Egemenlik ve Çocuk Bayramı'], ['05-01', 'Emek ve Dayanışma Günü'], ['05-19', 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı'], ['07-15', 'Demokrasi ve Millî Birlik Günü'], ['08-30', 'Zafer Bayramı'], ['10-29', 'Cumhuriyet Bayramı']],
        // CA — Canada (fériés fédéraux à date fixe ; Good Friday, Victoria Day,
        // Labour Day, Thanksgiving = mobiles, non seedés)
        'CA' => [['01-01', 'New Year\'s Day'], ['07-01', 'Canada Day'], ['11-11', 'Remembrance Day'], ['12-25', 'Christmas Day']],
        // ML — Mali (décrets ; Pâques/Aïds mobiles → #1812)
        'ML' => [['01-01', 'Jour de l\'an'], ['01-20', 'Journée des Forces armées'], ['03-01', 'Commémoration des martyrs'], ['03-26', 'Journée de la Démocratie'], ['05-01', 'Fête du Travail'], ['05-25', 'Journée de l\'Afrique'], ['09-22', 'Fête de l\'Indépendance'], ['12-25', 'Noël']],
        // GA — Gabon (décrets ; Pâques/Aïds mobiles → #1812)
        'GA' => [['01-01', 'Jour de l\'an'], ['03-12', 'Fête de la Rénovation'], ['04-17', 'Fête nationale de la Femme'], ['05-01', 'Fête du Travail'], ['08-15', 'Assomption'], ['08-17', 'Fête de l\'Indépendance'], ['11-01', 'Toussaint'], ['12-25', 'Noël']],
        // CG — Congo-Brazzaville (décrets ; Pâques/Aïds mobiles → #1812)
        'CG' => [['01-01', 'Jour de l\'an'], ['03-15', 'Fête de la Rénovation'], ['05-01', 'Fête du Travail'], ['06-10', 'Fête de la Réconciliation'], ['08-15', 'Fête de l\'Indépendance'], ['11-01', 'Toussaint'], ['12-25', 'Noël']],
        // BF — Burkina Faso : VOLONTAIREMENT non seedé — réforme légale 2026
        // (loi portant fêtes légales, 15 → 11 jours) : attendre le texte
        // officiel complet avant alimentation (issue #2255).
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
