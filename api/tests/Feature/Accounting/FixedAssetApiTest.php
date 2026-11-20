<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingDepreciation;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9668 — couche HTTP des immobilisations (#9377).
 *
 * `DepreciationTest` mesure le service (dotation, idempotence, plafond). Ce
 * fichier mesure le contrat **observable** des routes : code + payload, jamais
 * une methode privee du controleur, pour qu'il reste reecrivable.
 */
class FixedAssetApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        // Horloge maitrisee (#8396) : les dates de mise en service du fichier
        // sont des bornes metier explicites, pas "aujourd'hui moins N jours".
        // Les figer par rapport a un instant de reference evite la bombe a
        // retardement sans rendre les assertions relatives.
        $this->travelTo(Carbon::parse('2026-03-15 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function asset(Company $company, array $overrides = []): AccountingFixedAsset
    {
        /** @var AccountingFixedAsset $asset */
        $asset = AccountingFixedAsset::query()->create(array_merge([
            'company_id' => $company->id,
            'code' => 'IMM-0001',
            'label' => 'Ordinateur portable',
            'in_service_date' => '2026-01-15',
            'acquisition_cost' => 12000.0,
            'residual_value' => 0.0,
            'useful_life_months' => 12,
            'method' => AccountingFixedAsset::METHOD_LINEAR,
            'currency' => 'DZD',
            'status' => AccountingFixedAsset::STATUS_ACTIVE,
        ], $overrides));

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'IMM-9001',
            'label' => 'Vehicule utilitaire',
            'in_service_date' => '2026-02-01',
            'acquisition_cost' => 60000,
            'useful_life_months' => 60,
            'method' => 'linear',
            'currency' => 'DZD',
        ], $overrides);
    }

    public function test_comptable_can_create_fixed_asset(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/fixed-assets', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'IMM-9001');
        $response->assertJsonPath('data.label', 'Vehicule utilitaire');
        $response->assertJsonPath('data.status', AccountingFixedAsset::STATUS_ACTIVE);

        $this->assertDatabaseHas('accounting_fixed_assets', [
            'company_id' => $this->companyA->id,
            'code' => 'IMM-9001',
            'label' => 'Vehicule utilitaire',
        ]);
    }

    public function test_comptable_can_list_fixed_assets_and_filter_by_status(): void
    {
        $this->asset($this->companyA, ['code' => 'IMM-A1']);
        $this->asset($this->companyA, [
            'code' => 'IMM-A2',
            'status' => AccountingFixedAsset::STATUS_DISPOSED,
            'disposed_on' => '2026-06-30',
        ]);
        $this->asset($this->companyB, ['code' => 'IMM-B1']);

        Sanctum::actingAs($this->manager($this->companyA));

        $all = $this->getJson('/api/v1/accounting/fixed-assets');
        $all->assertStatus(200);
        $all->assertJsonCount(2, 'data');
        $all->assertJsonPath('total', 2);

        $active = $this->getJson('/api/v1/accounting/fixed-assets?status=active');
        $active->assertStatus(200);
        $active->assertJsonCount(1, 'data');
        $active->assertJsonPath('data.0.code', 'IMM-A1');
    }

    /**
     * La contrainte reelle est `unique(['company_id', 'code'])` : sans regle de
     * validation, la violation remontait en `SQLSTATE 23505`, donc en 500.
     */
    public function test_duplicate_code_within_tenant_is_rejected_as_422(): void
    {
        $this->asset($this->companyA, ['code' => 'IMM-DUP']);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/fixed-assets', $this->payload(['code' => 'IMM-DUP']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    /**
     * La meme reference peut exister chez deux societes : la borne est le
     * couple (societe, code), pas le code seul.
     */
    public function test_same_code_is_allowed_in_another_tenant(): void
    {
        $this->asset($this->companyA, ['code' => 'IMM-SHARED']);

        Sanctum::actingAs($this->manager($this->companyB));

        $this->postJson('/api/v1/accounting/fixed-assets', $this->payload(['code' => 'IMM-SHARED']))
            ->assertStatus(201);
    }

    public function test_asset_is_tenant_scoped_via_api(): void
    {
        $asset = $this->asset($this->companyA);

        Sanctum::actingAs($this->manager($this->companyB));

        $this->getJson('/api/v1/accounting/fixed-assets')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/accounting/fixed-assets/'.$asset->id)->assertStatus(404);
        $this->getJson('/api/v1/accounting/fixed-assets/'.$asset->id.'/schedule')->assertStatus(404);
        $this->putJson('/api/v1/accounting/fixed-assets/'.$asset->id, ['label' => 'Pirate'])->assertStatus(404);
        $this->deleteJson('/api/v1/accounting/fixed-assets/'.$asset->id)->assertStatus(404);

        $this->assertDatabaseHas('accounting_fixed_assets', [
            'id' => $asset->id,
            'label' => 'Ordinateur portable',
        ]);
    }

    public function test_show_exposes_depreciations(): void
    {
        $asset = $this->asset($this->companyA);

        AccountingDepreciation::query()->create([
            'company_id' => $this->companyA->id,
            'fixed_asset_id' => $asset->id,
            'period' => '2026-01',
            'entry_date' => '2026-01-31',
            'amount' => 1000.0,
            'accumulated_after' => 1000.0,
            'net_book_value_after' => 11000.0,
            'currency' => 'DZD',
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/fixed-assets/'.$asset->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.code', 'IMM-0001');
        $response->assertJsonCount(1, 'data.depreciations');
        $response->assertJsonPath('data.depreciations.0.period', '2026-01');
    }

    /**
     * Le cout, la duree et la date de mise en service ne sont pas modifiables :
     * les changer reecrirait l'historique des dotations deja comptabilisees.
     */
    public function test_update_does_not_rewrite_accounting_inputs(): void
    {
        $asset = $this->asset($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/fixed-assets/'.$asset->id, [
            'label' => 'Ordinateur renomme',
            'notes' => 'Restitue au service informatique',
            'acquisition_cost' => 1,
            'useful_life_months' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Ordinateur renomme');
        $response->assertJsonPath('data.notes', 'Restitue au service informatique');

        $this->assertDatabaseHas('accounting_fixed_assets', [
            'id' => $asset->id,
            'acquisition_cost' => 12000,
            'useful_life_months' => 12,
        ]);
    }

    public function test_schedule_is_computed_server_side(): void
    {
        $asset = $this->asset($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/fixed-assets/'.$asset->id.'/schedule');

        $response->assertStatus(200);
        $response->assertJsonCount(12, 'data');
        $response->assertJsonPath('data.0.period', '2026-01');
        $response->assertJsonPath('data.0.amount', 1000);
        $response->assertJsonPath('data.11.accumulated', 12000);
        $response->assertJsonPath('data.11.net_book_value', 0);
    }

    public function test_destroy_is_refused_once_a_depreciation_exists(): void
    {
        $asset = $this->asset($this->companyA);

        AccountingDepreciation::query()->create([
            'company_id' => $this->companyA->id,
            'fixed_asset_id' => $asset->id,
            'period' => '2026-01',
            'entry_date' => '2026-01-31',
            'amount' => 1000.0,
            'accumulated_after' => 1000.0,
            'net_book_value_after' => 11000.0,
            'currency' => 'DZD',
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->deleteJson('/api/v1/accounting/fixed-assets/'.$asset->id);

        $response->assertStatus(409);
        // La traduction doit etre resolue (une cle absente rendrait la cle
        // elle-meme) et ne doit pas contenir de placeholder, que le renderer
        // d'erreurs ne remplace jamais.
        $response->assertJsonPath('message', __('accounting.errors.fixed_asset_has_depreciations'));
        $this->assertStringNotContainsString('%s', (string) $response->json('message'));

        $this->assertDatabaseHas('accounting_fixed_assets', ['id' => $asset->id]);
    }

    public function test_destroy_without_depreciations_removes_the_asset(): void
    {
        $asset = $this->asset($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $this->deleteJson('/api/v1/accounting/fixed-assets/'.$asset->id)->assertStatus(204);

        $this->assertDatabaseMissing('accounting_fixed_assets', ['id' => $asset->id]);
    }

    public function test_validation_rejects_unsupported_method_and_invalid_amounts(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // `declining` n'est pas implemente : refus en amont, pas un 500.
        $this->postJson('/api/v1/accounting/fixed-assets', $this->payload(['method' => 'declining']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);

        $this->postJson('/api/v1/accounting/fixed-assets', $this->payload([
            'useful_life_months' => 0,
            'acquisition_cost' => -5,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['useful_life_months', 'acquisition_cost']);

        $this->postJson('/api/v1/accounting/fixed-assets', ['code' => 'MISSING'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label', 'in_service_date', 'acquisition_cost', 'useful_life_months', 'method']);
    }

    /**
     * `whereNumber('id')` existe pour que `fixed-assets/abc` ne soit pas pris
     * pour un identifiant : un segment non numerique n'atteint jamais le
     * controleur.
     */
    public function test_non_numeric_id_is_not_bound_to_the_controller(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/fixed-assets/abc')->assertStatus(404);
        $this->getJson('/api/v1/accounting/fixed-assets/1.5')->assertStatus(404);
    }

    public function test_employee_cannot_access_fixed_assets(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/fixed-assets')->assertStatus(403);
        $this->postJson('/api/v1/accounting/fixed-assets', $this->payload())->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/accounting/fixed-assets')->assertStatus(401);
    }
}
