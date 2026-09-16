<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Infrastructure\Services\TenantDeletionInventory;
use App\Modules\Platform\Infrastructure\Services\TenantDeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7475 — Suppression sûre d'un tenant : parcours en deux temps, inventaire
 * chiffré, confirmation par ressaisie du nom, journalisation.
 */
class PlatformCompanyDeletionApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        // Le service pose `search_path = public` (comme la console plateforme) :
        // on le restaure pour ne pas polluer les tests suivants.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }

        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_inventory_counts_what_would_be_destroyed(): void
    {
        $company = $this->suspendedCompany();

        Employee::factory()->count(3)->create(['company_id' => $company->id]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/deletion-inventory")
            ->assertOk()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.deletable', true)
            ->assertJsonPath('data.blocked_by', [])
            ->assertJsonPath('data.has_payroll_data', false)
            ->assertJsonPath('data.required_mode', TenantDeletionService::MODE_PURGE)
            ->assertJsonPath('data.counters.employees', 3);
    }

    public function test_deletion_is_refused_while_the_tenant_is_still_active(): void
    {
        // Critère 1 : aucune suppression sans désactivation préalable.
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'active']);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/deletion-inventory")
            ->assertOk()
            ->assertJsonPath('data.deletable', false)
            ->assertJsonPath('data.blocked_by', ['TENANT_NOT_DEACTIVATED']);

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => $company->name,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'TENANT_NOT_DEACTIVATED');

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_deletion_requires_typing_the_exact_company_name(): void
    {
        // Critère 2 : confirmation forte.
        $company = $this->suspendedCompany();

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => 'Pas le bon nom',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_name']);

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_tenant_holding_payroll_requires_an_explicit_mode(): void
    {
        // Critère 5 : sans choix explicite, on ne détruit pas de paie.
        $company = $this->suspendedCompany();
        $this->givePayrollRun($company);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/deletion-inventory")
            ->assertOk()
            ->assertJsonPath('data.has_payroll_data', true)
            ->assertJsonPath('data.required_mode', null);

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => $company->name,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'TENANT_DELETION_MODE_REQUIRED');

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_purge_erases_tenant_data_platform_rows_and_audits_the_operation(): void
    {
        // Critères 3 et 4 : inventaire chiffré, opération auditée et consultable.
        $company = $this->suspendedCompany();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $actor = $this->superAdmin();
        Sanctum::actingAs($actor, ['*'], 'super_admin_api');

        $response = $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => $company->name,
            'mode' => TenantDeletionService::MODE_PURGE,
            'reason' => 'Doublon de test',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mode', TenantDeletionService::MODE_PURGE)
            ->assertJsonPath('data.status', 'completed');

        // Plus rien du tenant nulle part.
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertSame(0, DB::table(TenantDeletionInventory::qualified('employees'))
            ->where('company_id', $company->id)
            ->count());
        $this->assertSame(0, DB::table(TenantDeletionInventory::qualified('employees'))
            ->where('id', $employee->id)
            ->count());
        $this->assertSame(
            0,
            (new TenantDeletionInventory)->tenantRowCount($company->id),
            'La purge doit laisser zéro ligne tenant derrière elle.'
        );

        // La preuve survit à la purge — y compris la JUSTIFICATION opérateur
        // (reliquat #7475 : `reason` était validée puis jetée).
        $this->assertDatabaseHas('tenant_deletion_audits', [
            'company_id' => $company->id,
            'mode' => TenantDeletionService::MODE_PURGE,
            'status' => 'completed',
            'actor_email' => $actor->email,
            'reason' => 'Doublon de test',
        ]);

        $this->getJson("/api/v1/platform/companies/{$company->id}/deletion-audits")
            ->assertNotFound();
    }

    public function test_dedicated_schema_tenant_is_refused_instead_of_deleting_nothing(): void
    {
        // Reliquat #7475 : le balayage ne couvre que le schéma `shared_tenants`.
        // Un tenant historique porté par un schéma DÉDIÉ serait déclaré
        // « purgé » sans qu'une seule ligne ne soit supprimée — un succès muet.
        // On refuse explicitement, et la tentative est auditée avec sa raison.
        $company = $this->suspendedCompany();

        // Ligne historique : la création est verrouillée depuis #7220
        // (`Company::booted()`), mais des lignes existantes portent encore ce mode.
        DB::table('public.companies')->where('id', $company->id)->update(['tenancy_type' => 'schema']);
        $company->refresh();

        $actor = $this->superAdmin();
        Sanctum::actingAs($actor, ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => $company->name,
            'mode' => TenantDeletionService::MODE_PURGE,
            'reason' => 'Tenant historique en schéma dédié',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'TENANT_DELETION_UNSUPPORTED_TENANCY');

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('tenant_deletion_audits', [
            'company_id' => $company->id,
            'status' => 'refused',
            'reason' => 'Tenant historique en schéma dédié',
        ]);
    }

    public function test_history_exposes_refused_operations(): void
    {
        // Critère 4 : le résultat est consultable — y compris une tentative
        // refusée, qui doit laisser une trace.
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'active']);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => $company->name,
        ])->assertStatus(409);

        $this->getJson("/api/v1/platform/companies/{$company->id}/deletion-audits")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'refused')
            ->assertJsonPath('data.0.mode', TenantDeletionService::MODE_PURGE);
    }

    public function test_anonymize_keeps_payroll_and_scrubs_identities(): void
    {
        $company = $this->suspendedCompany(['name' => 'Boulangerie du Port']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amine',
            'last_name' => 'Benali',
        ]);
        $this->givePayrollRun($company);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/companies/{$company->id}", [
            'confirm_name' => 'Boulangerie du Port',
            'mode' => TenantDeletionService::MODE_ANONYMIZE,
        ])->assertOk();

        // La société existe encore (la paie doit rester rattachable)…
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status' => 'expired']);

        // … mais anonymisée.
        $refreshed = Company::query()->find($company->id);
        $this->assertNotNull($refreshed);
        $this->assertSame('Espace anonymise ('.$company->id.')', $refreshed->name);
        // `email` et `city` sont NOT NULL : anonymisation par valeur neutre.
        $this->assertSame('anonymized+'.$company->id.'@invalid.local', $refreshed->email);
        $this->assertSame('', $refreshed->city);
        $this->assertNull($refreshed->phone);
        $this->assertNull($refreshed->notes);

        // L'identité de l'employé est effacée, sa ligne subsiste (FK de paie).
        $row = DB::table(TenantDeletionInventory::qualified('employees'))
            ->where('id', $employee->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Anonymise', $row->first_name);
        $this->assertSame('Anonymise', $row->last_name);
        $this->assertSame('anonymized+'.$employee->id.'@invalid.local', $row->email);

        // La paie est conservée.
        $this->assertSame(1, DB::table(TenantDeletionInventory::qualified('payroll_runs'))
            ->where('company_id', $company->id)
            ->count());
    }

    /**
     * Garde d'exhaustivité : toute table `public` portant `company_id` doit
     * être classée — supprimée avec le tenant, FK neutralisée, ou explicitement
     * conservée. Sans ce test, une table ajoutée demain laisserait des données
     * personnelles en base après une purge RGPD.
     */
    public function test_public_tenant_scope_is_fully_classified(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Classification vérifiée sur PostgreSQL uniquement.');
        }

        /** @var list<object{table_name: string}> $rows */
        $rows = DB::select(
            "SELECT c.table_name
               FROM information_schema.columns c
               JOIN information_schema.tables t
                 ON t.table_schema = c.table_schema AND t.table_name = c.table_name
              WHERE c.table_schema = 'public'
                AND c.column_name = 'company_id'
                AND t.table_type = 'BASE TABLE'
                -- Les tables du schéma tenant homonymes sont des artefacts de
                -- la fixture MVP (qui crée certaines tables tenant dans
                -- `public`) : ce sont des tables TENANT, hors de ce contrôle.
                AND NOT EXISTS (
                    SELECT 1 FROM information_schema.tables st
                     WHERE st.table_schema = 'shared_tenants'
                       AND st.table_name = c.table_name
                )"
        );

        $classified = array_merge(
            TenantDeletionService::PUBLIC_TENANT_TABLES,
            TenantDeletionService::PUBLIC_RETAINED_TABLES,
            ['tenant_deletion_audits'],
        );

        $unclassified = [];

        foreach ($rows as $row) {
            $table = (string) $row->table_name;

            if (! in_array($table, $classified, true)) {
                $unclassified[] = $table;
            }
        }

        $this->assertSame(
            [],
            $unclassified,
            'Tables `public` portant company_id non classées pour la purge #7475 : '
            .implode(', ', $unclassified)
            .'. Ajoutez-les à TenantDeletionService::PUBLIC_TENANT_TABLES ou à PUBLIC_RETAINED_TABLES.'
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function suspendedCompany(array $attributes = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge(['status' => 'suspended'], $attributes));

        return $company;
    }

    private function givePayrollRun(Company $company): void
    {
        DB::table(TenantDeletionInventory::qualified('payroll_runs'))->insert([
            'company_id' => $company->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
            'status' => 'validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function superAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        return $superAdmin;
    }
}
