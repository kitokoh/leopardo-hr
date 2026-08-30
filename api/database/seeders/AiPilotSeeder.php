<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * BC-23-D12 (issue #6241) — Seed pilote IA : environnement reproductible,
 * 100 % synthétique et démontrable pour la golden journey IA
 * (chat → action → confirmation → audit).
 *
 * Crée le tenant déterministe `ai-pilot-001` avec :
 *  - un principal (manager) + un employé de démonstration ;
 *  - le registre d'outils IA (AIToolRegistrySeeder) ;
 *  - une conversation IA synthétique + une entrée d'audit correspondante.
 *
 * Zéro donnée réelle, zéro secret (mot de passe démo documenté), valeurs 100 %
 * déterministes. Usage : `php artisan db:seed --class=AiPilotSeeder`
 * (environnements pilote/demo uniquement — jamais en production).
 *
 * Réentrant : si le tenant pilote existe déjà, skip (aucune donnée dupliquée).
 * Si les tables AI sont absentes : warning + skip gracieux (base en cours de
 * migration).
 */
class AiPilotSeeder extends Seeder
{
    private const SHARED_SCHEMA = 'shared_tenants';

    private const PILOT_SLUG = 'ai-pilot-001';

    public function run(): void
    {
        $existing = Company::query()->where('slug', self::PILOT_SLUG)->first();

        if ($existing instanceof Company) {
            $this->command?->warn("Pilote IA {$existing->slug} déjà présent — skip (réentrant).");

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'AI Pilot 001',
            'slug' => self::PILOT_SLUG,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedUsers($company);
            $this->seedAiData($company);
        });

        $this->command?->info('Pilote IA créé : ai-pilot-001');
    }

    private function seedUsers(Company $company): void
    {
        $now = now();

        // Mot de passe DÉMO documenté (parcours pilote) — jamais un secret réel.
        $demoHash = Hash::make('pilot123');

        DB::table('employees')->insert([
            [
                'company_id' => $company->id,
                'first_name' => 'Lina',
                'last_name' => 'Principal',
                'email' => 'principal@ai-pilot-001.leopardo.test',
                'role' => 'manager',
                'manager_role' => 'principal',
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'first_name' => 'Yacine',
                'last_name' => 'Employe',
                'email' => 'employe@ai-pilot-001.leopardo.test',
                'role' => 'employee',
                'manager_role' => null,
                'status' => 'active',
                'password_hash' => $demoHash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function seedAiData(Company $company): void
    {
        if (! Schema::hasTable('ai_tool_registry')) {
            $this->command?->warn('Tables AI absentes (en attente des migrations AI) — données IA du pilote ignorées.');

            return;
        }

        $companyId = (string) $company->id;
        $now = now();

        // Registre d'outils IA (réentrant : pas d'upsert si déjà présents).
        $this->callOnce(AIToolRegistrySeeder::class);

        $principal = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('email', 'principal@ai-pilot-001.leopardo.test')
            ->first();

        $conversationId = DB::table('ai_conversations')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $principal->id,
            'title' => 'Parcours pilote IA',
            'messages' => json_encode([
                ['role' => 'user', 'content' => 'Prépare ma demande de congé'],
                ['role' => 'assistant', 'content' => 'Voici le résumé des étapes : demande, confirmation, audit.'],
            ]),
            'context' => json_encode(['company_id' => $companyId]),
            'token_count' => 48,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('ai_audit_logs')->insert([
            'company_id' => $companyId,
            'user_id' => $principal->id,
            'conversation_id' => $conversationId,
            'prompt' => 'Prépare ma demande de congé',
            'response' => 'Voici le résumé des étapes : demande, confirmation, audit.',
            'tools_called' => json_encode([]),
            'provider' => 'internal',
            'model' => 'pilot-synthetic',
            'input_tokens' => 22,
            'output_tokens' => 26,
            'cost_cents' => 0,
            'duration_ms' => 0,
            'error' => null,
            'workflow' => null,
            'created_at' => $now,
        ]);
    }
}
