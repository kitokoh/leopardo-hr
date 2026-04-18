<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DemoCompanySeeder — Données de démo réalistes pour le développement local
 *
 * ⚠️ LOCAL UNIQUEMENT — Ne jamais exécuter en production
 *
 * Crée :
 * - 2 entreprises (shared + schema Enterprise)
 * - 1 manager principal + 2 managers (RH et Dept) + 15 employés
 * - Départements, postes, plannings, sites
 * - Données de pointage du mois en cours
 * - Absences, avances, tâches, paies
 *
 * USAGE : php artisan db:seed --class=DemoCompanySeeder
 */
class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $allowOutsideLocal = filter_var(env('ALLOW_DEMO_SEEDING', false), FILTER_VALIDATE_BOOLEAN);
        $allowOnce = filter_var(env('DEMO_SEED_ONCE', false), FILTER_VALIDATE_BOOLEAN);

        if (! app()->environment('local', 'development', 'testing') && ! $allowOutsideLocal && ! $allowOnce) {
            $this->command->error('🚨 DemoCompanySeeder interdit en production !');
            $this->command->warn('Définir ALLOW_DEMO_SEEDING=true ou DEMO_SEED_ONCE=true pour un seed exceptionnel de test.');
            return;
        }

        $this->command->info('');
        $this->command->info('🎭 Création des données de démo...');

        DB::statement("SET search_path TO public");

        // ── Récupérer le plan Starter ──────────────────────────────────────
        $starterPlan = DB::table('public.plans')->where('name', 'Starter')->first();
        if (! $starterPlan) {
            $this->command->error('Plans non trouvés — exécuter DatabaseSeeder d\'abord');
            return;
        }

        // ════════════════════════════════════════════════════════════════════
        // ENTREPRISE 1 — "TechCorp Algérie" (plan Starter, mode shared)
        // ════════════════════════════════════════════════════════════════════
        $company1Id = (string) Str::uuid();
        $schema1 = 'shared_tenants'; // Mode shared

        DB::table('companies')->insert([
            'id'                 => $company1Id,
            'name'               => 'TechCorp Algérie SARL',
            'slug'               => 'techcorp-algerie',
            'sector'             => 'Technologie',
            'country'            => 'DZ',
            'city'               => 'Alger',
            'address'            => '12 Rue Didouche Mourad, Alger Centre',
            'email'              => 'rh@techcorp-algerie.dz',
            'phone'              => '+213 21 23 45 67',
            'plan_id'            => $starterPlan->id,
            'schema_name'        => $schema1,
            'tenancy_type'       => 'shared',
            'status'             => 'active',
            'subscription_start' => now()->startOfMonth(),
            'subscription_end'   => now()->addMonths(12),
            'language'           => 'fr',
            'timezone'           => 'Africa/Algiers',
            'currency'           => 'DZD',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // S'assurer que le schéma shared_tenants existe
        DB::statement("CREATE SCHEMA IF NOT EXISTS shared_tenants");
        DB::statement("SET search_path TO shared_tenants, public");

        // ── Département ────────────────────────────────────────────────────
        $deptDevId = DB::table('departments')->insertGetId([
            'company_id' => $company1Id,
            'name'       => 'Développement',
            'created_at' => now(),
        ]);

        $deptRhId = DB::table('departments')->insertGetId([
            'company_id' => $company1Id,
            'name'       => 'Ressources Humaines',
            'created_at' => now(),
        ]);

        // ── Postes ─────────────────────────────────────────────────────────
        $posteDirId = DB::table('positions')->insertGetId([
            'company_id'    => $company1Id,
            'name'          => 'Directeur Général',
            'department_id' => $deptDevId,
            'created_at'    => now(),
        ]);

        $posteDevId = DB::table('positions')->insertGetId([
            'company_id'    => $company1Id,
            'name'          => 'Développeur Senior',
            'department_id' => $deptDevId,
            'created_at'    => now(),
        ]);

        $posteRhId = DB::table('positions')->insertGetId([
            'company_id'    => $company1Id,
            'name'          => 'Responsable RH',
            'department_id' => $deptRhId,
            'created_at'    => now(),
        ]);

        // ── Planning ───────────────────────────────────────────────────────
        $scheduleId = DB::table('schedules')->insertGetId([
            'company_id'                => $company1Id,
            'name'                      => 'Horaire standard 8h-17h',
            'start_time'                => '08:00:00',
            'end_time'                  => '17:00:00',
            'break_minutes'             => 60,
            'work_days'                 => json_encode([1, 2, 3, 4, 5]),
            'late_tolerance_minutes'    => 15,
            'overtime_threshold_daily'  => 8.00,
            'overtime_threshold_weekly' => 40.00,
            'is_default'                => true,
            'created_at'                => now(),
        ]);

        // ── Site ───────────────────────────────────────────────────────────
        $siteId = DB::table('sites')->insertGetId([
            'company_id'  => $company1Id,
            'name'        => 'Siège Social Alger',
            'address'     => '12 Rue Didouche Mourad, Alger',
            'gps_lat'     => 36.7529,
            'gps_lng'     => 3.0420,
            'gps_radius_m' => 100,
            'created_at'  => now(),
        ]);

        // ── Manager Principal ──────────────────────────────────────────────
        $managerIdDb = DB::table('employees')->insertGetId([
            'company_id'     => $company1Id,
            'matricule'      => 'EMP-001',
            'first_name'     => 'Ahmed',
            'last_name'      => 'Benali',
            'email'          => 'ahmed.benali@techcorp-algerie.dz',
            'phone'          => '+213 661 23 45 67',
            'password_hash'  => Hash::make('password123'),
            'role'           => 'manager',
            'manager_role'   => 'principal',
            'department_id'  => $deptDevId,
            'position_id'    => $posteDirId,
            'schedule_id'    => $scheduleId,
            'site_id'        => $siteId,
            'contract_type'  => 'CDI',
            'contract_start' => '2020-01-01',
            'salary_base'    => 180000.00, // DZD
            'leave_balance'  => 12.5,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // ── user_lookups — dispatch auth ───────────────────────────────────
        DB::statement("SET search_path TO public");
        DB::table('user_lookups')->insert([
            'email'       => 'ahmed.benali@techcorp-algerie.dz',
            'company_id'  => $company1Id,
            'schema_name' => $schema1,
            'employee_id' => $managerIdDb,
            'role'        => 'manager',
        ]);

        DB::statement("SET search_path TO shared_tenants, public");

        // ── Mise à jour du manager du département ──────────────────────────
        DB::table('departments')
            ->where('id', $deptDevId)
            ->update(['manager_id' => $managerIdDb]);

        // ── Manager RH ─────────────────────────────────────────────────────
        $managerRhId = DB::table('employees')->insertGetId([
            'company_id'    => $company1Id,
            'matricule'     => 'EMP-002',
            'first_name'    => 'Fatima',
            'last_name'     => 'Meziane',
            'email'         => 'fatima.meziane@techcorp-algerie.dz',
            'password_hash' => Hash::make('password123'),
            'role'          => 'manager',
            'manager_role'  => 'rh',
            'department_id' => $deptRhId,
            'position_id'   => $posteRhId,
            'schedule_id'   => $scheduleId,
            'site_id'       => $siteId,
            'manager_id'    => $managerIdDb,
            'contract_type' => 'CDI',
            'contract_start' => '2021-03-01',
            'salary_base'   => 120000.00,
            'leave_balance' => 18.0,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::statement("SET search_path TO public");
        DB::table('user_lookups')->insert([
            'email'       => 'fatima.meziane@techcorp-algerie.dz',
            'company_id'  => $company1Id,
            'schema_name' => $schema1,
            'employee_id' => $managerRhId,
            'role'        => 'manager',
        ]);
        DB::statement("SET search_path TO shared_tenants, public");

        // ── 5 Employés ─────────────────────────────────────────────────────
        $employeeData = [
            ['Karim', 'Aouad',    'karim.aouad@techcorp-algerie.dz',    'EMP-003', 95000],
            ['Amina', 'Brahimi',  'amina.brahimi@techcorp-algerie.dz',   'EMP-004', 88000],
            ['Youcef','Kaddour',  'youcef.kaddour@techcorp-algerie.dz',  'EMP-005', 92000],
            ['Nadia', 'Tlemçani', 'nadia.tlemcani@techcorp-algerie.dz',  'EMP-006', 85000],
            ['Mehdi', 'Saadi',    'mehdi.saadi@techcorp-algerie.dz',     'EMP-007', 78000],
        ];

        $employeeIds = [];
        foreach ($employeeData as $i => [$first, $last, $email, $mat, $salary]) {
            $empId = DB::table('employees')->insertGetId([
                'company_id'    => $company1Id,
                'matricule'     => $mat,
                'first_name'    => $first,
                'last_name'     => $last,
                'email'         => $email,
                'password_hash' => Hash::make('password123'),
                'role'          => 'employee',
                'department_id' => $deptDevId,
                'position_id'   => $posteDevId,
                'schedule_id'   => $scheduleId,
                'site_id'       => $siteId,
                'manager_id'    => $managerIdDb,
                'contract_type' => 'CDI',
                'contract_start' => now()->subMonths(rand(6, 36))->format('Y-m-d'),
                'salary_base'   => $salary,
                'leave_balance' => round(rand(0, 25) / 2, 1),
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $employeeIds[] = $empId;

            DB::statement("SET search_path TO public");
            DB::table('user_lookups')->insert([
                'email'       => $email,
                'company_id'  => $company1Id,
                'schema_name' => $schema1,
                'employee_id' => $empId,
                'role'        => 'employee',
            ]);
            DB::statement("SET search_path TO shared_tenants, public");
        }

        // ── Pointages du mois en cours (20 derniers jours ouvrés) ─────────
        $allEmployeeIds = array_merge([$managerIdDb, $managerRhId], $employeeIds);
        $workingDays = $this->getWorkingDaysThisMonth();

        foreach ($allEmployeeIds as $empId) {
            foreach ($workingDays as $day) {
                $isLate  = rand(0, 10) === 0; // 10% de retard
                $isAbsent = rand(0, 20) === 0; // 5% d'absence

                if ($isAbsent) continue;

                $checkIn = $day->copy()->setTime(8, $isLate ? rand(20, 45) : rand(0, 14), 0);
                $checkOut = $checkIn->copy()->addHours(rand(8, 10));

                DB::table('attendance_logs')->insertOrIgnore([
                    'company_id'    => $company1Id,
                    'employee_id'   => $empId,
                    'date'          => $day->format('Y-m-d'),
                    'session_number' => 1,
                    'check_in'      => $checkIn->toIso8601String(),
                    'check_out'     => $checkOut->toIso8601String(),
                    'method'        => 'mobile',
                    'status'        => $isLate ? 'late' : 'ontime',
                    'hours_worked'  => round($checkOut->diffInMinutes($checkIn) / 60, 2),
                    'overtime_hours' => max(0, round($checkOut->diffInHours($checkIn) - 8, 2)),
                    'late_minutes'  => $isLate ? $checkIn->diffInMinutes($day->copy()->setTime(8, 0, 0)) : 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // ── 2 Demandes d'absence ───────────────────────────────────────────
        $absenceTypeId = DB::table('absence_types')->insertGetId([
            'company_id'     => $company1Id,
            'name'           => 'Congé annuel',
            'code'           => 'CONGE_ANNUEL',
            'is_paid'        => true,
            'deducts_leave'  => true,
            'requires_proof' => false,
            'created_at'     => now(),
        ]);

        DB::table('absences')->insert([
            'company_id'      => $company1Id,
            'employee_id'     => $employeeIds[0],
            'absence_type_id' => $absenceTypeId,
            'start_date'      => now()->addDays(5)->format('Y-m-d'),
            'end_date'        => now()->addDays(9)->format('Y-m-d'),
            'days_count'      => 5,
            'status'          => 'pending',
            'reason'          => 'Vacances familiales',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('absences')->insert([
            'company_id'      => $company1Id,
            'employee_id'     => $employeeIds[1],
            'absence_type_id' => $absenceTypeId,
            'start_date'      => now()->subDays(10)->format('Y-m-d'),
            'end_date'        => now()->subDays(8)->format('Y-m-d'),
            'days_count'      => 3,
            'status'          => 'approved',
            'approved_by'     => $managerRhId,
            'reason'          => 'Motif personnel',
            'created_at'      => now()->subDays(12),
            'updated_at'      => now()->subDays(10),
        ]);

        // ── 1 Avance sur salaire ────────────────────────────────────────────
        DB::table('salary_advances')->insert([
            'company_id'         => $company1Id,
            'employee_id'        => $employeeIds[2],
            'amount'             => 30000.00, // DZD
            'reason'             => 'Achat équipement informatique personnel',
            'status'             => 'active',
            'approved_by'        => $managerRhId,
            'decision_comment'   => 'Approuvé — remboursement 3 mois',
            'repayment_months'   => 3,
            'monthly_deduction'  => 10000.00,
            'amount_remaining'   => 20000.00,
            'repayment_plan'     => json_encode([
                ['month' => now()->format('Y-m'),             'amount' => 10000, 'paid' => true],
                ['month' => now()->addMonth()->format('Y-m'), 'amount' => 10000, 'paid' => false],
                ['month' => now()->addMonths(2)->format('Y-m'), 'amount' => 10000, 'paid' => false],
            ]),
            'created_at'         => now()->subMonth(),
            'updated_at'         => now(),
        ]);

        // ── 1 Bulletin de paie (mois précédent) ────────────────────────────
        $lastMonth = now()->subMonth();
        DB::table('payrolls')->insert([
            'company_id'        => $company1Id,
            'employee_id'       => $managerIdDb,
            'period_month'      => (int) $lastMonth->format('m'),
            'period_year'       => (int) $lastMonth->format('Y'),
            'gross_salary'      => 180000.00,
            'overtime_amount'   => 0,
            'bonuses'           => json_encode([]),
            'deductions'        => json_encode([]),
            'cotisations'       => json_encode([
                ['name' => 'Retraite (salarié)',  'rate' => 0.09,  'base' => 180000, 'amount' => 16200],
                ['name' => 'Assurance chômage',  'rate' => 0.005, 'base' => 180000, 'amount' =>   900],
                ['name' => 'Mutuelle (AMO)',      'rate' => 0.015, 'base' => 180000, 'amount' =>  2700],
            ]),
            'ir_amount'         => 15400.00,
            'advance_deduction' => 0,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
            'net_salary'        => 144800.00, // 180000 - 19800 (cotis.) - 15400 (IR)
            'status'            => 'validated',
            'validated_by'      => $managerIdDb,
            'validated_at'      => now()->subDays(5),
            'created_at'        => now()->subDays(7),
            'updated_at'        => now()->subDays(5),
        ]);

        // ── Paramètres entreprise ──────────────────────────────────────────
        DB::table('company_settings')->upsert([
            [
                'key' => 'onboarding_completed',
                'value' => 'true',
                'value_type' => 'boolean',
                'updated_at' => now(),
            ],
            [
                'key' => 'payroll_day',
                'value' => '25',
                'value_type' => 'integer',
                'updated_at' => now(),
            ],
            [
                'key' => 'legal_id',
                'value' => '123456789',
                'value_type' => 'string',
                'updated_at' => now(),
            ],
            [
                'key' => 'legal_id_label',
                'value' => 'NIF',
                'value_type' => 'string',
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'value_type', 'updated_at']);

        DB::statement("SET search_path TO public");

        $this->command->info('');
        $this->command->info('✅ Données de démo créées :');
        $this->command->info('   Entreprise : TechCorp Algérie SARL (Starter/shared)');
        $this->command->info('   Employés   : 7 (1 manager principal + 1 RH + 5 employés)');
        $this->command->info('');
        $this->command->info('   Connexion manager principal :');
        $this->command->info('   Email    : ahmed.benali@techcorp-algerie.dz');
        $this->command->info('   Password : password123');
        $this->command->info('');
        $this->command->info('   Connexion manager RH :');
        $this->command->info('   Email    : fatima.meziane@techcorp-algerie.dz');
        $this->command->info('   Password : password123');
        $this->command->info('');
        $this->command->info('   Connexion employé :');
        $this->command->info('   Email    : karim.aouad@techcorp-algerie.dz');
        $this->command->info('   Password : password123');
    }

    /**
     * Retourne les jours ouvrés (lundi-vendredi) du mois en cours
     * jusqu'à aujourd'hui.
     */
    private function getWorkingDaysThisMonth(): array
    {
        $days = [];
        $start = now()->startOfMonth();
        $end = now()->subDay(); // Pas aujourd'hui (pas encore pointé)

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // 1=Lundi ... 5=Vendredi
            if ($date->dayOfWeek >= 1 && $date->dayOfWeek <= 5) {
                $days[] = $date->copy();
            }
        }

        return array_slice($days, -20); // Limiter aux 20 derniers jours ouvrés
    }
}
