<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * #5743 (CRM-PRE) — Seed pilote CRM : environnement reproductible, non
 * sensible et démontrable pour agents, reviewers et pilotes.
 *
 * Crée DEUX tenants de démonstration déterministes (`crm-pilot-alpha`,
 * `crm-pilot-beta`) avec :
 *  - un principal + un rh par tenant ;
 *  - un pipeline CRM par défaut (étapes whitelistées) ;
 *  - le parcours de démo complet : account → contacts (1 primaire) →
 *    leads → opportunities (dont une gagnée) → tasks ;
 *  - zéro donnée réelle, zéro secret, valeurs 100 % déterministes.
 *
 * Usage : php artisan db:seed --class=CrmPilotSeeder   (environnements
 * pilote/demo uniquement — jamais en production).
 *
 * Réentrant : si un tenant pilote existe déjà, il est conservé (skip).
 * Les tables CRM doivent exister (issues #5708/#5709) : sinon warning et
 * skip gracieux — le seeder ne plante jamais sur une base en cours de
 * migration.
 */
class CrmPilotSeeder extends Seeder
{
    private const SHARED_SCHEMA = 'shared_tenants';

    private const PILOT_STAGES = ['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost'];

    /**
     * @var list<array{slug: string, name: string, domain: string}>
     */
    private const PILOTS = [
        ['slug' => 'crm-pilot-alpha', 'name' => 'CRM Pilot Alpha', 'domain' => 'alpha.crm-pilot.leopardo.test'],
        ['slug' => 'crm-pilot-beta', 'name' => 'CRM Pilot Beta', 'domain' => 'beta.crm-pilot.leopardo.test'],
    ];

    public function run(): void
    {
        foreach (self::PILOTS as $pilot) {
            $this->seedPilot($pilot['slug'], $pilot['name'], $pilot['domain']);
        }
    }

    private function seedPilot(string $slug, string $name, string $domain): void
    {
        $existing = Company::query()->where('slug', $slug)->first();

        if ($existing instanceof Company) {
            $this->command?->warn("Pilote {$slug} déjà présent — skip (réentrant).");

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $domain): void {
            $this->seedUsers($company, $domain);
            $this->seedCrmData($company, $domain);
        });

        $this->command?->info("Pilote CRM créé : {$slug}");
    }

    private function seedUsers(Company $company, string $domain): void
    {
        $now = now();

        // Mot de passe DÉMO documenté (parcours pilote) — jamais un secret réel.
        $demoHash = Hash::make('pilot123');

        DB::table('employees')->insert([
            [
                'company_id' => $company->id,
                'first_name' => 'Amina',
                'last_name' => 'Principal',
                'email' => "principal@{$domain}",
                'role' => 'manager',
                'manager_role' => 'principal',
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'first_name' => 'Karim',
                'last_name' => 'RH',
                'email' => "rh@{$domain}",
                'role' => 'manager',
                'manager_role' => 'rh',
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function seedCrmData(Company $company, string $domain): void
    {
        if (! $this->crmTablesPresent()) {
            $this->command?->warn('Tables CRM absentes (en attente de #5708/#5709) — données CRM du pilote ignorées.');

            return;
        }

        $companyId = (string) $company->id;
        $now = now();

        // ── Account ──────────────────────────────────────────────────────────
        $accountId = DB::table('crm_accounts')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Alpha Industries',
            'status' => 'active',
            'email' => "ops@{$domain}",
            'phone' => '+213555010203',
            'notes' => 'Compte de démonstration du pilote CRM.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Contacts (1 primaire) ────────────────────────────────────────────
        $jeanId = DB::table('crm_contacts')->insertGetId([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => "jean.dupont@{$domain}",
            'phone' => '+213555040506',
            'title' => 'Directeur achats',
            'status' => 'active',
            'is_primary' => true,
            'notes' => 'Contact principal de démonstration.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_contacts')->insert([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => "marie.martin@{$domain}",
            'phone' => '+213555070809',
            'title' => 'Responsable logistique',
            'status' => 'active',
            'is_primary' => false,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Leads ────────────────────────────────────────────────────────────
        $sarahId = DB::table('crm_leads')->insertGetId([
            'company_id' => $companyId,
            'first_name' => 'Sarah',
            'last_name' => 'Khan',
            'company_name' => 'Global Export',
            'email' => "sarah.khan@{$domain}",
            'phone' => '+213555111213',
            'source' => 'manual',
            'status' => 'qualified',
            'notes' => 'Lead de démonstration — prêt pour la conversion (#5717).',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_leads')->insert([
            'company_id' => $companyId,
            'first_name' => 'Omar',
            'last_name' => 'Benali',
            'company_name' => 'Tech Atlas',
            'email' => "omar.benali@{$domain}",
            'phone' => '+213555141516',
            'source' => 'web',
            'status' => 'new',
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Pipeline (défaut) ────────────────────────────────────────────────
        $pipelineId = DB::table('crm_pipelines')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Pipeline Pilote',
            'is_default' => true,
            'stages' => json_encode(self::PILOT_STAGES, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Opportunities (une gagnée) ───────────────────────────────────────
        DB::table('crm_opportunities')->insert([
            'company_id' => $companyId,
            // pipeline_id NON setté : la colonne est uuid dans la migration
            // #5709 alors que les PK sont bigint — jamais SET (leçon CRM).
            'name' => 'Deal Global Export',
            'stage' => 'negotiation',
            'amount' => 120000.00,
            'currency' => 'DZD',
            'expected_close_date' => now()->addDays(14)->toDateString(),
            'status' => 'open',
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('crm_opportunities')->insert([
            'company_id' => $companyId,
            'name' => 'Deal Tech Atlas',
            'stage' => 'won',
            'amount' => 45000.00,
            'currency' => 'DZD',
            'expected_close_date' => now()->subDays(2)->toDateString(),
            'status' => 'open',
            'notes' => 'Signé — démonstration.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Tasks ────────────────────────────────────────────────────────────
        $principalId = DB::table('employees')
            ->where('company_id', $company->id)
            ->where('manager_role', 'principal')
            ->value('id');

        DB::table('crm_tasks')->insert([
            [
                'company_id' => $companyId,
                'title' => 'Appeler Sarah Khan',
                'description' => 'Relance de démonstration sur le lead Global Export.',
                'due_at' => now()->addDay()->toDateTimeString(),
                'assignee_id' => $principalId,
                'done' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $companyId,
                'title' => 'Préparer la proposition Tech Atlas',
                'description' => 'Proposition de démonstration à envoyer avant la fin de semaine.',
                'due_at' => now()->addDays(7)->toDateTimeString(),
                'assignee_id' => $principalId,
                'done' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Références pour la doc de parcours (évite les "unused").
        unset($jeanId, $sarahId);
    }

    private function crmTablesPresent(): bool
    {
        foreach (['crm_accounts', 'crm_contacts', 'crm_leads', 'crm_pipelines', 'crm_opportunities', 'crm_tasks'] as $table) {
            if (! schemaTableExists($table)) {
                return false;
            }
        }

        return true;
    }
}
