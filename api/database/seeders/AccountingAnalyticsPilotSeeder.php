<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Application\Actions\SeedAccountingDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * BC-22-D12 (issue #6244) — seed pilote Analytics & Reporting.
 *
 * Golden journey du reporting comptable sur données 100 % synthétiques :
 *   - tenant pilote déterministe `analytics-pilot-001` (DZ/DZD, shared) ;
 *   - vitrine comptable réaliste via `SeedAccountingDemoData` (contacts,
 *     factures émises/payées/partielles, avoir, paiements rapprochés) ;
 *   - comptes pilotes documentés (`comptable@analytics-pilot-001.leopardo.test`
 *     / `pilot123`) pour la recette UI end-to-end.
 *
 * Garanties (MAT-012) :
 *   - zéro donnée réelle, zéro secret — valeurs 100 % déterministes ;
 *   - anti-production : refus explicite en environnement `production` ;
 *   - réentrant : tenant pilote déjà présent → skip gracieux.
 *
 * Usage : php artisan db:seed --class=AccountingAnalyticsPilotSeeder
 *         (environnements pilote/demo uniquement).
 */
class AccountingAnalyticsPilotSeeder extends Seeder
{
    private const SHARED_SCHEMA = 'shared_tenants';

    private const PILOT_SLUG = 'analytics-pilot-001';

    private const PILOT_NAME = 'Analytics Pilot 001';

    private const PILOT_DOMAIN = 'analytics-pilot-001.leopardo.test';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seed pilote interdit en production (MAT-012, #6244).');

            return;
        }

        $existing = Company::query()->where('slug', self::PILOT_SLUG)->first();

        if ($existing instanceof Company) {
            $this->command?->warn('Pilote Analytics déjà présent ({$existing->slug}) — skip (réentrant).');

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => self::PILOT_NAME,
            'slug' => self::PILOT_SLUG,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedUsers($company);
            $this->seedAccountingVitrine($company);
        });

        $this->command?->info('Pilote Analytics créé : '.$company->slug.' (comptable@'.self::PILOT_DOMAIN.' / pilot123).');
    }

    private function seedUsers(Company $company): void
    {
        $now = now();

        // Mot de passe DÉMO documenté (parcours pilote) — jamais un secret réel.
        $demoHash = Hash::make('pilot123');

        DB::table('employees')->insert([
            [
                'company_id' => $company->id,
                'first_name' => 'Salima',
                'last_name' => 'Comptable',
                'email' => 'comptable@'.self::PILOT_DOMAIN,
                'role' => 'manager',
                'manager_role' => 'comptable',
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'first_name' => 'Yacine',
                'last_name' => 'Principal',
                'email' => 'principal@'.self::PILOT_DOMAIN,
                'role' => 'manager',
                'manager_role' => 'principal',
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function seedAccountingVitrine(Company $company): void
    {
        if (schemaTableExists('accounting_documents') === false) {
            $this->command?->warn('Tables comptables absentes — vitrine du pilote Analytics ignorée.');

            return;
        }

        (new SeedAccountingDemoData)->seed($company);
    }
}
