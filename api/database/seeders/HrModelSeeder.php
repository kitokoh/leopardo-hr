<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HrModelSeeder — Modèles RH préconfigurés par pays
 *
 * Chaque modèle contient :
 * - cotisations : taux salariales et patronales
 * - ir_brackets : tranches IR progressives
 * - leave_rules : règles congés annuels
 * - holiday_calendar : jours fériés nationaux
 * - working_hours : seuils heures sup
 *
 * Source : docs/dossierdeConception/05_regles_metier/15_GUIDE_AJOUT_PAYS.md
 */
class HrModelSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET search_path TO public');

        $models = [

            // ─────────────────────────────────────────────────────────────────
            // ALGÉRIE (DZ) — Modèle principal MVP
            // Source légale : Code du travail algérien, CNAS, IRG 2024
            // ─────────────────────────────────────────────────────────────────
            [
                'country_code' => 'DZ',
                'name' => 'Modèle Algérie',
                'cotisations' => json_encode([
                    'salariales' => [
                        ['name' => 'Retraite (salarié)',    'rate' => 0.09,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Assurance chômage',     'rate' => 0.005, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Mutuelle (AMO)',        'rate' => 0.015, 'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'patronales' => [
                        ['name' => 'Retraite (patronal)',   'rate' => 0.17,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Assurance chômage',     'rate' => 0.01,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Maladie (CNAS)',        'rate' => 0.125, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Accidents de travail',  'rate' => 0.015, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Formation (FCP)',       'rate' => 0.01,  'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'total_salarial' => 0.11,   // 9% + 0.5% + 1.5%
                    'total_patronal' => 0.325,  // 17% + 1% + 12.5% + 1.5% + 1%
                ]),
                'ir_brackets' => json_encode([
                    ['min' => 0, 'max' => 240000, 'rate' => 0.00, 'deduction' => 0],
                    ['min' => 240001, 'max' => 480000, 'rate' => 0.23, 'deduction' => 55200],
                    ['min' => 480001, 'max' => 960000, 'rate' => 0.27, 'deduction' => 74400],
                    ['min' => 960001, 'max' => 1920000, 'rate' => 0.30, 'deduction' => 103200],
                    ['min' => 1920001, 'max' => 3840000, 'rate' => 0.33, 'deduction' => 160800],
                    ['min' => 3840001, 'max' => null, 'rate' => 0.35, 'deduction' => 237600],
                    // Valeurs annuelles en DZD — diviser par 12 pour le mensuel
                    // Abattement forfaitaire : 40% du brut (minimum 120 000 DZD/an)
                ]),
                'leave_rules' => json_encode([
                    'accrual_rate_monthly' => 2.5,   // 30 jours/an = 2.5j/mois
                    'initial_balance' => 0,
                    'carry_over' => true,
                    'max_balance' => 45,    // Plafond légal algérien
                    'min_notice_days' => 15,
                    'legal_minimum_days' => 30,    // Minimum légal Algérie
                ]),
                'holiday_calendar' => json_encode([
                    ['date' => '2026-01-01', 'name' => 'Jour de l\'An',               'is_recurring' => true],
                    ['date' => '2026-01-12', 'name' => 'Yennayer (Nouvel An berbère)', 'is_recurring' => true],
                    ['date' => '2026-05-01', 'name' => 'Fête du Travail',             'is_recurring' => true],
                    ['date' => '2026-07-05', 'name' => 'Fête de l\'Indépendance',    'is_recurring' => true],
                    ['date' => '2026-11-01', 'name' => 'Fête de la Révolution',      'is_recurring' => true],
                    // Fêtes islamiques variables (Aïd, Mawlid...) — à calculer annuellement
                ]),
                'working_hours' => json_encode([
                    'weekly_hours' => 40,
                    'daily_hours' => 8,
                    'overtime_rate_first' => 1.50,   // 50% au-delà de 8h/jour
                    'overtime_rate_night' => 1.75,   // 75% nuit + jours fériés
                    'overtime_threshold_daily' => 8.0,
                    'overtime_threshold_weekly' => 40.0,
                ]),
            ],

            // ─────────────────────────────────────────────────────────────────
            // MAROC (MA)
            // Source légale : Code du travail marocain, CNSS, IR 2024
            // ─────────────────────────────────────────────────────────────────
            [
                'country_code' => 'MA',
                'name' => 'Modèle Maroc',
                'cotisations' => json_encode([
                    'salariales' => [
                        ['name' => 'CNSS (salarié)',       'rate' => 0.0448, 'base' => 'gross_salary', 'ceiling' => 6000],
                        ['name' => 'AMO (salarié)',        'rate' => 0.0226, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'CIMR (facultatif)',    'rate' => 0.03,   'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'patronales' => [
                        ['name' => 'CNSS (patronal)',      'rate' => 0.1857, 'base' => 'gross_salary', 'ceiling' => 6000],
                        ['name' => 'AMO (patronal)',       'rate' => 0.0226, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Taxe formation prof.', 'rate' => 0.016,  'base' => 'gross_salary', 'ceiling' => null],
                    ],
                ]),
                'ir_brackets' => json_encode([
                    ['min' => 0, 'max' => 30000, 'rate' => 0.00, 'deduction' => 0],
                    ['min' => 30001, 'max' => 50000, 'rate' => 0.10, 'deduction' => 3000],
                    ['min' => 50001, 'max' => 60000, 'rate' => 0.20, 'deduction' => 8000],
                    ['min' => 60001, 'max' => 80000, 'rate' => 0.30, 'deduction' => 14000],
                    ['min' => 80001, 'max' => 180000, 'rate' => 0.34, 'deduction' => 17200],
                    ['min' => 180001, 'max' => null, 'rate' => 0.38, 'deduction' => 24400],
                    // Valeurs annuelles en MAD
                ]),
                'leave_rules' => json_encode([
                    'accrual_rate_monthly' => 1.5,   // 18 jours/an minimum légal
                    'initial_balance' => 0,
                    'carry_over' => true,
                    'max_balance' => 36,
                    'min_notice_days' => 15,
                    'legal_minimum_days' => 18,
                ]),
                'holiday_calendar' => json_encode([
                    ['date' => '2026-01-01', 'name' => 'Nouvel An',                   'is_recurring' => true],
                    ['date' => '2026-01-11', 'name' => 'Manifeste de l\'Indépendance', 'is_recurring' => true],
                    ['date' => '2026-05-01', 'name' => 'Fête du Travail',             'is_recurring' => true],
                    ['date' => '2026-07-30', 'name' => 'Fête du Trône',               'is_recurring' => true],
                    ['date' => '2026-08-14', 'name' => 'Allégeance Oued Eddahab',     'is_recurring' => true],
                    ['date' => '2026-08-20', 'name' => 'Révolution du Roi et du Peuple', 'is_recurring' => true],
                    ['date' => '2026-08-21', 'name' => 'Fête de la Jeunesse',         'is_recurring' => true],
                    ['date' => '2026-11-06', 'name' => 'Marche Verte',                'is_recurring' => true],
                    ['date' => '2026-11-18', 'name' => 'Fête de l\'Indépendance',     'is_recurring' => true],
                ]),
                'working_hours' => json_encode([
                    'weekly_hours' => 44,
                    'daily_hours' => 8.8,
                    'overtime_rate_first' => 1.25,
                    'overtime_rate_holiday' => 1.50,
                    'overtime_threshold_daily' => 8.8,
                    'overtime_threshold_weekly' => 44.0,
                ]),
            ],

            // ─────────────────────────────────────────────────────────────────
            // TUNISIE (TN)
            // ─────────────────────────────────────────────────────────────────
            [
                'country_code' => 'TN',
                'name' => 'Modèle Tunisie',
                'cotisations' => json_encode([
                    'salariales' => [
                        ['name' => 'CNSS (salarié)',  'rate' => 0.0918, 'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'patronales' => [
                        ['name' => 'CNSS (patronal)', 'rate' => 0.1643, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'TFP',             'rate' => 0.02,   'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'FOPROLOS',        'rate' => 0.01,   'base' => 'gross_salary', 'ceiling' => null],
                    ],
                ]),
                'ir_brackets' => json_encode([
                    ['min' => 0, 'max' => 5000, 'rate' => 0.00, 'deduction' => 0],
                    ['min' => 5001, 'max' => 20000, 'rate' => 0.26, 'deduction' => 1300],
                    ['min' => 20001, 'max' => 30000, 'rate' => 0.28, 'deduction' => 1700],
                    ['min' => 30001, 'max' => 50000, 'rate' => 0.32, 'deduction' => 2900],
                    ['min' => 50001, 'max' => null, 'rate' => 0.35, 'deduction' => 4400],
                ]),
                'leave_rules' => json_encode([
                    'accrual_rate_monthly' => 2.0,
                    'initial_balance' => 0,
                    'carry_over' => true,
                    'max_balance' => 30,
                    'min_notice_days' => 15,
                    'legal_minimum_days' => 24,
                ]),
                'holiday_calendar' => json_encode([
                    ['date' => '2026-01-01', 'name' => 'Jour de l\'An',             'is_recurring' => true],
                    ['date' => '2026-03-20', 'name' => 'Fête de l\'Indépendance',  'is_recurring' => true],
                    ['date' => '2026-04-09', 'name' => 'Fête des Martyrs',         'is_recurring' => true],
                    ['date' => '2026-05-01', 'name' => 'Fête du Travail',          'is_recurring' => true],
                    ['date' => '2026-06-01', 'name' => 'Fête de la Victoire',      'is_recurring' => true],
                    ['date' => '2026-07-25', 'name' => 'Fête de la République',    'is_recurring' => true],
                    ['date' => '2026-08-13', 'name' => 'Fête de la Femme',         'is_recurring' => true],
                    ['date' => '2026-10-15', 'name' => 'Fête de l\'Évacuation',   'is_recurring' => true],
                ]),
                'working_hours' => json_encode([
                    'weekly_hours' => 48,
                    'daily_hours' => 8,
                    'overtime_rate_first' => 1.75,
                    'overtime_threshold_daily' => 8.0,
                    'overtime_threshold_weekly' => 48.0,
                ]),
            ],

            // ─────────────────────────────────────────────────────────────────
            // FRANCE (FR)
            // ─────────────────────────────────────────────────────────────────
            [
                'country_code' => 'FR',
                'name' => 'Modèle France',
                'cotisations' => json_encode([
                    'salariales' => [
                        ['name' => 'Retraite de base',        'rate' => 0.0690, 'base' => 'capped_T1', 'ceiling' => 3666],
                        ['name' => 'Retraite complémentaire', 'rate' => 0.0315, 'base' => 'capped_T1', 'ceiling' => 3666],
                        ['name' => 'Assurance chômage',       'rate' => 0.00,   'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Maladie/maternité',       'rate' => 0.00,   'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'CSG déductible',          'rate' => 0.0680, 'base' => '0.9825', 'ceiling' => null],
                        ['name' => 'CSG non déductible',      'rate' => 0.0240, 'base' => '0.9825', 'ceiling' => null],
                    ],
                    'patronales' => [
                        ['name' => 'Retraite de base',        'rate' => 0.0855, 'base' => 'capped_T1', 'ceiling' => 3666],
                        ['name' => 'Retraite complémentaire', 'rate' => 0.0472, 'base' => 'capped_T1', 'ceiling' => 3666],
                        ['name' => 'Assurance chômage',       'rate' => 0.0405, 'base' => 'capped_T1', 'ceiling' => 3666],
                        ['name' => 'Maladie/maternité',       'rate' => 0.13,   'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Accident du travail',     'rate' => 0.022,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Allocations familiales',  'rate' => 0.0525, 'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'note' => 'Taux 2024 — à vérifier avec expert-comptable pour cas particuliers',
                ]),
                'ir_brackets' => json_encode([
                    // PAS utilisé directement — prélèvement à la source géré par l'employeur
                    // Taux neutre par défaut si le salarié ne communique pas son taux personnalisé
                    ['min' => 0, 'max' => 1450, 'rate' => 0.00, 'deduction' => 0],
                    ['min' => 1451, 'max' => 1566, 'rate' => 0.05, 'deduction' => 0],
                    ['min' => 1567, 'max' => 1724, 'rate' => 0.075, 'deduction' => 0],
                    ['min' => 1725, 'max' => 1996, 'rate' => 0.10, 'deduction' => 0],
                    ['min' => 1997, 'max' => 2592, 'rate' => 0.125, 'deduction' => 0],
                    ['min' => 2593, 'max' => 2722, 'rate' => 0.155, 'deduction' => 0],
                    ['min' => 2723, 'max' => 3134, 'rate' => 0.17, 'deduction' => 0],
                    ['min' => 3135, 'max' => 3689, 'rate' => 0.19, 'deduction' => 0],
                    ['min' => 3690, 'max' => 5065, 'rate' => 0.21, 'deduction' => 0],
                    ['min' => 5066, 'max' => null, 'rate' => 0.24, 'deduction' => 0],
                    // Valeurs mensuelles en EUR — taux neutre 2024
                ]),
                'leave_rules' => json_encode([
                    'accrual_rate_monthly' => 2.5,   // 30 jours/an (5 semaines)
                    'initial_balance' => 0,
                    'carry_over' => true,
                    'max_balance' => 30,
                    'min_notice_days' => 30,
                    'legal_minimum_days' => 25,    // 25 jours ouvrés
                ]),
                'holiday_calendar' => json_encode([
                    ['date' => '2026-01-01', 'name' => 'Jour de l\'An',         'is_recurring' => true],
                    ['date' => '2026-05-01', 'name' => 'Fête du Travail',       'is_recurring' => true],
                    ['date' => '2026-05-08', 'name' => 'Victoire 1945',         'is_recurring' => true],
                    ['date' => '2026-07-14', 'name' => 'Fête Nationale',        'is_recurring' => true],
                    ['date' => '2026-08-15', 'name' => 'Assomption',            'is_recurring' => true],
                    ['date' => '2026-11-01', 'name' => 'Toussaint',             'is_recurring' => true],
                    ['date' => '2026-11-11', 'name' => 'Armistice 1918',        'is_recurring' => true],
                    ['date' => '2026-12-25', 'name' => 'Noël',                  'is_recurring' => true],
                    // Lundi de Pâques, Ascension, Lundi de Pentecôte = dates variables
                ]),
                'working_hours' => json_encode([
                    'weekly_hours' => 35,
                    'daily_hours' => 7,
                    'overtime_rate_first_8h' => 1.25,  // De 35h à 43h
                    'overtime_rate_beyond_8h' => 1.50,  // Au-delà de 43h
                    'overtime_threshold_weekly' => 35.0,
                ]),
            ],

            // ─────────────────────────────────────────────────────────────────
            // TURQUIE (TR)
            // ─────────────────────────────────────────────────────────────────
            [
                'country_code' => 'TR',
                'name' => 'Modèle Turquie',
                'cotisations' => json_encode([
                    'salariales' => [
                        ['name' => 'SGK Retraite (salarié)',   'rate' => 0.09,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'SGK Santé (salarié)',      'rate' => 0.05,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Assurance chômage (İŞKUR)', 'rate' => 0.01, 'base' => 'gross_salary', 'ceiling' => null],
                    ],
                    'patronales' => [
                        ['name' => 'SGK Retraite (patronal)',  'rate' => 0.11,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'SGK Santé (patronal)',     'rate' => 0.075, 'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Assurance chômage',        'rate' => 0.02,  'base' => 'gross_salary', 'ceiling' => null],
                        ['name' => 'Prévoyance AT',            'rate' => 0.02,  'base' => 'gross_salary', 'ceiling' => null],
                    ],
                ]),
                'ir_brackets' => json_encode([
                    // Taux gelir vergisi 2024 (en TRY annuel)
                    ['min' => 0, 'max' => 110000, 'rate' => 0.15, 'deduction' => 0],
                    ['min' => 110001, 'max' => 230000, 'rate' => 0.20, 'deduction' => 5500],
                    ['min' => 230001, 'max' => 870000, 'rate' => 0.27, 'deduction' => 21600],
                    ['min' => 870001, 'max' => 3000000, 'rate' => 0.35, 'deduction' => 91200],
                    ['min' => 3000001, 'max' => null, 'rate' => 0.40, 'deduction' => 241200],
                ]),
                'leave_rules' => json_encode([
                    'accrual_rate_monthly' => 1.167,  // 14 jours/an minimum légal
                    'initial_balance' => 0,
                    'carry_over' => false,   // Pas de report en droit turc standard
                    'max_balance' => 14,
                    'min_notice_days' => 15,
                    'legal_minimum_days' => 14,
                ]),
                'holiday_calendar' => json_encode([
                    ['date' => '2026-01-01', 'name' => 'Yılbaşı (Nouvel An)',           'is_recurring' => true],
                    ['date' => '2026-04-23', 'name' => 'Ulusal Egemenlik ve Çocuk Bayramı', 'is_recurring' => true],
                    ['date' => '2026-05-01', 'name' => 'İşçi Bayramı',                 'is_recurring' => true],
                    ['date' => '2026-05-19', 'name' => 'Atatürk\'ü Anma ve Gençlik',  'is_recurring' => true],
                    ['date' => '2026-07-15', 'name' => 'Demokrasi Bayramı',            'is_recurring' => true],
                    ['date' => '2026-08-30', 'name' => 'Zafer Bayramı',                'is_recurring' => true],
                    ['date' => '2026-10-29', 'name' => 'Cumhuriyet Bayramı',           'is_recurring' => true],
                    // Ramazan Bayramı + Kurban Bayramı = dates variables (calendrier lunaire)
                ]),
                'working_hours' => json_encode([
                    'weekly_hours' => 45,
                    'daily_hours' => 9,
                    'overtime_rate_first' => 1.50,
                    'overtime_threshold_daily' => 9.0,
                    'overtime_threshold_weekly' => 45.0,
                ]),
            ],
        ];

        foreach ($models as $model) {
            DB::table('hr_model_templates')->updateOrInsert(
                ['country_code' => $model['country_code']],
                $model
            );
        }

        $this->command->info('✅ Modèles RH créés : DZ, MA, TN, FR, TR (cotisations + IR + congés + jours fériés)');
    }
}
