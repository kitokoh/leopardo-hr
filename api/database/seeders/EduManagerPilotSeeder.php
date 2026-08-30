<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Database\Seeders\Concerns\GuardsPilotSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-012 (#5870) — Seed pilote EduManager : environnement reproductible,
 * non sensible et démontrable (BC-16 EDU, runbook RUNBOOK_PILOT_EDUMANAGER).
 *
 * Crée le tenant déterministe `edu-pilot-001` avec un campus, des élèves et
 * des guardians synthétiques.
 *
 * ⚠️ Le module EduManager (migrations `edu_*`) est livré par la PR
 * `pm/merge-edu-manager` (#5974) — tant que les tables n'existent pas sur
 * main, ce seeder skip gracieusement (jamais d'erreur sur une base en cours
 * de migration) et devient actif dès le merge du socle.
 *
 * Réentrant : si le tenant pilote existe déjà, il est conservé (skip).
 * Nettoyable : `pilot:seed --solution=edu --clean`.
 */
class EduManagerPilotSeeder extends Seeder
{
    use GuardsPilotSeeding;

    public const SLUG = 'edu-pilot-001';

    private const SHARED_SCHEMA = 'shared_tenants';

    public function run(): void
    {
        $this->assertPilotEnvironmentAllowed('edu');

        $existing = Company::query()->where('slug', self::SLUG)->first();

        if ($existing instanceof Company) {
            $this->command?->warn("Pilote {$this->slug()} déjà présent — skip (réentrant).");

            return;
        }

        if (! Schema::hasTable('edu_campuses')) {
            $this->command?->warn('Module EduManager non livré sur main (en attente #5974) — seed pilote edu ignoré (skip gracieux).');

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'EduManager Pilot 001',
            'slug' => self::SLUG,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
            'features' => ['rh' => true, 'edu' => true],
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedEduData($company);
        });

        $this->command?->info('Seed pilote EduManager créé : '.self::SLUG);
    }

    private function seedEduData(Company $company): void
    {
        $companyId = (string) $company->id;
        $domain = 'edu.pilot.leopardo.test';
        $now = now();

        // Mot de passe DÉMO documenté (parcours pilote) — jamais un secret réel.
        $demoHash = Hash::make('pilot123');

        DB::table('employees')->insert([
            'company_id' => $companyId,
            'first_name' => 'Lynda',
            'last_name' => 'Directrice',
            'email' => "directrice@{$domain}",
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'password_hash' => $demoHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $campusId = DB::table('edu_campuses')->insertGetId([
            'company_id' => $companyId,
            'code' => 'CMP-ALG-01',
            'name' => 'Campus Pilote Alger',
            'address' => 'Zone pilote, Alger',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $guardianId = DB::table('edu_guardians')->insertGetId([
            'company_id' => $companyId,
            'first_name' => 'Karim',
            'last_name' => 'Tuteur',
            'email' => "tuteur@{$domain}",
            'contact_reference' => 'GAR-001',
            'relationship_code' => 'parent',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $students = [
            ['student_number' => 'STU-0001', 'display_name' => 'Élève Pilote 01'],
            ['student_number' => 'STU-0002', 'display_name' => 'Élève Pilote 02'],
        ];

        foreach ($students as $student) {
            $studentId = DB::table('edu_students')->insertGetId([
                'company_id' => $companyId,
                'student_number' => $student['student_number'],
                'display_name' => $student['display_name'],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('edu_student_guardians')->insert([
                'company_id' => $companyId,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
                'relationship_code' => 'parent',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function slug(): string
    {
        return self::SLUG;
    }
}
