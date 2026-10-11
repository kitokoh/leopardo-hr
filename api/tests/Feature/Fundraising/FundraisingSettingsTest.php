<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;
use App\Modules\Fundraising\Domain\Models\FundraisingSettings;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Verticale FUNDRAISING — paramétrage tenant du module (FUND-070) :
 * GET/PUT /fundraising/settings (ligne unique, défauts sans persistance,
 * validation, RBAC + feature flag) et consommation des réglages par la
 * surface publique (méthode désactivée 422, mur des soutiens masqué,
 * garde-fous montant tenant, montants suggérés par défaut à la création).
 */
class FundraisingSettingsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(bool $withFeature = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        if ($withFeature) {
            $company->setFeature('fundraising', true);
            $company->save();
        }

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function putSettings(Company $company, array $overrides = []): FundraisingSettings
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $overrides): FundraisingSettings {
            /** @var FundraisingSettings $settings */
            $settings = FundraisingSettings::query()->create(array_merge([
                'company_id' => $company->id,
                'enabled_methods' => ['card', 'mobile_money', 'bank_transfer', 'cash'],
                'default_min_amount' => null,
                'default_max_amount' => null,
                'default_suggested_amounts' => null,
                'allow_anonymous' => true,
                'allow_messages' => true,
                'show_supporters' => true,
            ], $overrides));

            return $settings;
        });
    }

    public function test_show_returns_defaults_without_persisting(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->getJson('/api/v1/fundraising/settings')
            ->assertOk()
            ->assertJsonPath('data.enabled_methods', ['card', 'mobile_money', 'bank_transfer', 'cash'])
            ->assertJsonPath('data.default_min_amount', null)
            ->assertJsonPath('data.default_max_amount', null)
            ->assertJsonPath('data.allow_anonymous', true)
            ->assertJsonPath('data.allow_messages', true)
            ->assertJsonPath('data.show_supporters', true);

        // Aucune ligne créée par une simple lecture.
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(0, FundraisingSettings::query()->count());
        });
    }

    public function test_update_persists_and_stays_unique(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->putJson('/api/v1/fundraising/settings', [
            'enabled_methods' => ['mobile_money'],
            'default_min_amount' => 500,
            'default_max_amount' => 500000,
            'default_suggested_amounts' => [1000, 5000, 10000],
            'allow_anonymous' => false,
            'allow_messages' => true,
            'show_supporters' => false,
        ])->assertOk()
            ->assertJsonPath('data.enabled_methods', ['mobile_money'])
            ->assertJsonPath('data.allow_anonymous', false)
            ->assertJsonPath('data.show_supporters', false);

        $this->getJson('/api/v1/fundraising/settings')
            ->assertOk()
            ->assertJsonPath('data.default_min_amount', 500)
            ->assertJsonPath('data.default_max_amount', 500000)
            ->assertJsonPath('data.default_suggested_amounts', [1000, 5000, 10000]);

        // Second PUT : la même ligne est mise à jour (unicité tenant).
        $this->putJson('/api/v1/fundraising/settings', [
            'enabled_methods' => ['card', 'cash'],
            'default_min_amount' => null,
            'default_max_amount' => null,
            'default_suggested_amounts' => null,
            'allow_anonymous' => true,
            'allow_messages' => true,
            'show_supporters' => true,
        ])->assertOk();

        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(1, FundraisingSettings::query()->count());
        });
    }

    public function test_update_validates_payload(): void
    {
        $company = $this->company();
        $this->principal($company);

        // Aucune méthode offerte → refusé (désactiver le module plutôt).
        $this->putJson('/api/v1/fundraising/settings', ['enabled_methods' => []])
            ->assertStatus(422);

        // Méthode inconnue.
        $this->putJson('/api/v1/fundraising/settings', ['enabled_methods' => ['bitcoin']])
            ->assertStatus(422);

        // Incohérence min/max.
        $this->putJson('/api/v1/fundraising/settings', [
            'enabled_methods' => ['cash'],
            'default_min_amount' => 5000,
            'default_max_amount' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['default_max_amount']);
    }

    public function test_rbac_and_feature_flag_are_fail_closed(): void
    {
        $company = $this->company();

        // Employé simple : pas de rôle gestionnaire → 403.
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/fundraising/settings')->assertStatus(403);
        $this->putJson('/api/v1/fundraising/settings', ['enabled_methods' => ['cash']])->assertStatus(403);

        // Verticale non activée pour le tenant → middleware fail-closed 403.
        $companyB = $this->company(false);
        $this->principal($companyB);
        $this->getJson('/api/v1/fundraising/settings')->assertStatus(403);
    }

    public function test_contribution_rejects_disabled_method_and_exposes_settings_publicly(): void
    {
        $company = $this->company();
        $this->putSettings($company, ['enabled_methods' => ['cash']]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            Fundraiser::query()->create([
                'company_id' => $company->id,
                'slug' => 'aide-settings-1',
                'title' => 'Cagnotte paramétrée',
                'beneficiary_name' => 'Fatou Ndiaye',
                'currency' => 'XOF',
                'status' => FundraiserStatus::ACTIVE,
                'published_at' => now(),
            ]);
        });
        FundraiserPublicLink::query()->create([
            'slug' => 'aide-settings-1',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        // La fiche publique expose exactement les méthodes offertes.
        $this->getJson('/api/v1/public/fundraisers/aide-settings-1')
            ->assertOk()
            ->assertJsonPath('data.enabled_methods', ['cash']);

        // Mobile money désactivé → 422 code stable (le front peut réagir).
        $this->postJson('/api/v1/public/fundraisers/aide-settings-1/contribute', [
            'amount' => 5000,
            'payment_method' => 'mobile_money',
            'contributor_phone' => '+221770000000',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'CONTRIBUTION_METHOD_DISABLED');

        // Espèces (offert) → initiation manuelle acceptée.
        $this->postJson('/api/v1/public/fundraisers/aide-settings-1/contribute', [
            'amount' => 5000,
            'payment_method' => 'cash',
        ])->assertStatus(201);
    }

    public function test_supporters_wall_hidden_when_disabled(): void
    {
        $company = $this->company();

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            Fundraiser::query()->create([
                'company_id' => $company->id,
                'slug' => 'aide-settings-2',
                'title' => 'Cagnotte discrète',
                'beneficiary_name' => 'Fatou Ndiaye',
                'currency' => 'XOF',
                'status' => FundraiserStatus::ACTIVE,
                'published_at' => now(),
            ]);
        });
        FundraiserPublicLink::query()->create([
            'slug' => 'aide-settings-2',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        // Visible par défaut.
        $this->getJson('/api/v1/public/fundraisers/aide-settings-2/supporters')->assertOk();

        // Désactivé par le paramétrage → 404 uniforme.
        $this->putSettings($company, ['show_supporters' => false]);
        $this->getJson('/api/v1/public/fundraisers/aide-settings-2/supporters')->assertStatus(404);
    }

    public function test_tenant_min_max_defaults_apply_when_fundraiser_silent(): void
    {
        $company = $this->company();
        $this->putSettings($company, ['default_min_amount' => 2000, 'enabled_methods' => ['cash']]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            Fundraiser::query()->create([
                'company_id' => $company->id,
                'slug' => 'aide-settings-3',
                'title' => 'Cagnotte sans bornes propres',
                'beneficiary_name' => 'Fatou Ndiaye',
                'currency' => 'XOF',
                'status' => FundraiserStatus::ACTIVE,
                'published_at' => now(),
            ]);
        });
        FundraiserPublicLink::query()->create([
            'slug' => 'aide-settings-3',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        // 1500 < plancher tenant 2000 → 422 (le défaut config était 100).
        $this->postJson('/api/v1/public/fundraisers/aide-settings-3/contribute', [
            'amount' => 1500,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CONTRIBUTION_AMOUNT');

        $this->postJson('/api/v1/public/fundraisers/aide-settings-3/contribute', [
            'amount' => 2500,
            'payment_method' => 'cash',
        ])->assertStatus(201);
    }

    public function test_default_suggested_amounts_applied_on_create(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->putSettings($company, ['default_suggested_amounts' => [500, 1000, 2000]]);

        $id = $this->postJson('/api/v1/fundraising/fundraisers', [
            'title' => 'Cagnotte avec défauts tenant',
            'beneficiary_name' => 'Fatou Ndiaye',
            'goal_amount' => 50000,
            'currency' => 'XOF',
        ])->assertStatus(201)
            ->json('data.id');

        $this->getJson('/api/v1/fundraising/fundraisers/'.$id)
            ->assertOk()
            ->assertJsonPath('data.suggested_amounts', [500, 1000, 2000]);
    }
}
