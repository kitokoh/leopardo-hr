<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9764 — Étapes normalisées du pipeline CRM par défaut.
 *
 * `crm_pipeline_stages` était créée par la migration #5709 mais **jamais
 * alimentée** : 0 ligne sur tous les tenants. Le read model du dashboard
 * (#5721) joignait dessus, donc même en renseignant `stage_id` la jointure
 * n'aurait rien trouvé.
 *
 * Ce seeder crée, pour chaque tenant :
 *  - un pipeline par défaut (`is_default = true`) s'il n'en existe aucun ;
 *  - les six étapes de `CrmOpportunityStage`, dans l'ordre de l'énumération,
 *    avec `won`/`lost` marquées comme états terminaux.
 *
 * Idempotent : un pipeline déjà présent n'est pas dupliqué, et une étape
 * existante (même `pipeline_id` + `position`) n'est pas réinsérée. Rejouable
 * sans effet de bord, y compris sur une base partiellement seedée.
 *
 * Usage :
 *   php artisan db:seed --class=CrmPipelineStagesSeeder
 *   php artisan crm:seed-pipeline-stages --all-tenants   (voir la commande)
 */
class CrmPipelineStagesSeeder extends Seeder
{
    /**
     * Libellés affichés, alignés sur `CrmDashboardReadModel::STAGE_LABELS`
     * et sur le kanban `/crm/pipeline`.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'prospecting' => 'Prospection',
        'qualification' => 'Qualification',
        'proposal' => 'Proposition',
        'negotiation' => 'Négociation',
        'won' => 'Gagné',
        'lost' => 'Perdu',
    ];

    /**
     * @var array<string, string>
     */
    private const COLORS = [
        'prospecting' => '#3b82f6',
        'qualification' => '#8b5cf6',
        'proposal' => '#f59e0b',
        'negotiation' => '#f97316',
        'won' => '#10b981',
        'lost' => '#64748b',
    ];

    public function run(): void
    {
        $companies = Company::query()->where('status', 'active')->get();

        if ($companies->isEmpty()) {
            $this->command->warn('CrmPipelineStagesSeeder : aucun tenant actif.');

            return;
        }

        $tenantManager = app(TenantManager::class);
        $seeded = 0;

        foreach ($companies as $company) {
            // #9764 — la vérification des tables doit se faire DANS le contexte
            // tenant : hors `withinTenant`, le search_path ne contient pas le
            // schéma des tables CRM et `Schema::hasTable` répond false à tort.
            $tenantManager->withinTenant($company, function () use ($company, &$seeded): void {
                if (! Schema::hasTable('crm_pipelines') || ! Schema::hasTable('crm_pipeline_stages')) {
                    return;
                }

                $this->seedForCompany((string) $company->id);
                $seeded++;
            });
        }

        if ($seeded === 0) {
            $this->command->warn('CrmPipelineStagesSeeder skipped : tables CRM absentes.');

            return;
        }

        $this->command->info("CrmPipelineStagesSeeder : {$seeded} tenant(s) trait\u00e9(s).");
    }

    /**
     * Crée le pipeline par défaut et ses étapes pour UN tenant.
     *
     * @return int|null id du pipeline par défaut, null si les tables manquent
     */
    public function seedForCompany(string $companyId): ?int
    {
        $pipelineId = DB::table('crm_pipelines')
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->value('id');

        if ($pipelineId === null) {
            $pipelineId = DB::table('crm_pipelines')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Pipeline commercial',
                'is_default' => true,
                'stages' => json_encode(
                    array_map(static fn (CrmOpportunityStage $s): string => $s->value, CrmOpportunityStage::cases()),
                    JSON_THROW_ON_ERROR
                ),
                'description' => 'Pipeline par défaut créé par CrmPipelineStagesSeeder (#9764).',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (CrmOpportunityStage::cases() as $position => $stage) {
            $exists = DB::table('crm_pipeline_stages')
                ->where('pipeline_id', $pipelineId)
                ->where('position', $position)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('crm_pipeline_stages')->insert([
                'company_id' => $companyId,
                'pipeline_id' => $pipelineId,
                'name' => self::LABELS[$stage->value],
                'position' => $position,
                'color' => self::COLORS[$stage->value],
                'is_won' => $stage === CrmOpportunityStage::Won,
                'is_lost' => $stage === CrmOpportunityStage::Lost,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $pipelineId;
    }
}
