<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * #5743 (CRM-PRE) — Seed de CHARGE du module CRM (benchmark).
 *
 * SÉPARÉ des fixtures fonctionnelles : crée une entreprise dédiée
 * (`benchmark-crm-dz`) avec des volumes synthétiques d'accounts, contacts,
 * leads, opportunités et tâches en inserts groupés bruts (aucun événement
 * Eloquent, aucune donnée pilote).
 *
 * Usage : php artisan db:seed --class=CrmBenchmarkSeeder
 *
 * Réentrant : si l'entreprise benchmark existe déjà, skip.
 */
class CrmBenchmarkSeeder extends Seeder
{
    public function run(int $accounts = 500, int $contactsPerAccount = 3, int $leads = 800, int $opportunities = 300, int $tasks = 600): void
    {
        $slug = 'benchmark-crm-dz';

        /** @var Company|null $existing */
        $existing = Company::query()->where('slug', $slug)->first();
        if ($existing instanceof Company) {
            $this->command?->warn("Benchmark CRM {$slug} déjà présent — skip (réentrant).");

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Benchmark CRM DZ',
            'slug' => $slug,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $accounts, $contactsPerAccount, $leads, $opportunities, $tasks): void {
            if (! schemaTableExists('crm_accounts')) {
                $this->command?->warn('Tables CRM absentes (en attente de #5708/#5709) — benchmark ignoré.');

                return;
            }

            $now = now();
            $companyId = (string) $company->id;

            $accountIds = [];
            for ($i = 1; $i <= $accounts; $i++) {
                $accountIds[] = DB::table('crm_accounts')->insertGetId([
                    'company_id' => $companyId,
                    'name' => "Benchmark Account {$i}",
                    'status' => $i % 10 === 0 ? 'inactive' : 'active',
                    'email' => "account{$i}@benchmark-crm.leopardo.test",
                    'phone' => '+213555'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $contactRows = [];
            foreach ($accountIds as $index => $accountId) {
                for ($c = 1; $c <= $contactsPerAccount; $c++) {
                    $contactRows[] = [
                        'company_id' => $companyId,
                        'account_id' => $accountId,
                        'first_name' => "Contact {$index}",
                        'last_name' => "Lot {$c}",
                        'email' => "contact{$index}.{$c}@benchmark-crm.leopardo.test",
                        'phone' => '+2135559'.str_pad((string) ($index * 10 + $c), 5, '0', STR_PAD_LEFT),
                        'title' => null,
                        'status' => 'active',
                        'is_primary' => $c === 1,
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($contactRows, 500) as $chunk) {
                DB::table('crm_contacts')->insert($chunk);
            }

            $leadRows = [];
            for ($i = 1; $i <= $leads; $i++) {
                $leadRows[] = [
                    'company_id' => $companyId,
                    'first_name' => "Lead {$i}",
                    'last_name' => 'Benchmark',
                    'company_name' => "Benchmark Co {$i}",
                    'email' => "lead{$i}@benchmark-crm.leopardo.test",
                    'phone' => null,
                    'source' => 'import',
                    'status' => $i % 5 === 0 ? 'qualified' : 'new',
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($leadRows, 500) as $chunk) {
                DB::table('crm_leads')->insert($chunk);
            }

            $pipelineId = DB::table('crm_pipelines')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Pipeline Benchmark',
                'is_default' => true,
                'stages' => json_encode(['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $opportunityRows = [];
            for ($i = 1; $i <= $opportunities; $i++) {
                $opportunityRows[] = [
                    'company_id' => $companyId,
                    // pipeline_id NON setté : colonne uuid (#5709) vs PK bigint.
                    'name' => "Benchmark Deal {$i}",
                    'stage' => ['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost'][$i % 6],
                    'amount' => (float) ($i * 1234.56),
                    'currency' => 'DZD',
                    'expected_close_date' => now()->addDays($i % 60)->toDateString(),
                    'status' => 'open',
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($opportunityRows, 500) as $chunk) {
                DB::table('crm_opportunities')->insert($chunk);
            }

            $taskRows = [];
            for ($i = 1; $i <= $tasks; $i++) {
                $taskRows[] = [
                    'company_id' => $companyId,
                    'title' => "Benchmark Task {$i}",
                    'description' => null,
                    'due_at' => now()->addDays($i % 30)->toDateTimeString(),
                    'assignee_id' => null,
                    'done' => $i % 4 === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($taskRows, 500) as $chunk) {
                DB::table('crm_tasks')->insert($chunk);
            }
        });

        $this->command?->info("Benchmark CRM créé : {$slug} ({$accounts} accounts, {$leads} leads, {$opportunities} opportunités, {$tasks} tâches).");
    }
}
