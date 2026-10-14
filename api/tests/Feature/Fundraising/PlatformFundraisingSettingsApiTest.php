<?php

declare(strict_types=1);

namespace Tests\Feature\Fundraising;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Modules\Fundraising\Domain\Models\FundraisingSettings;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8441 — paramétrage FUNDRAISING par l'admin plateforme dans le contexte
 * d'une entreprise cliente : `GET/PUT /platform/companies/{company}/fundraising/settings`.
 *
 * Le défaut corrigé : la fiche entreprise de la console admin ouvre l'écran
 * de paramétrage cagnottes qui appelait les routes TENANT — l'admin éditait
 * les réglages de son tenant de session, jamais ceux de l'entreprise
 * consultée. Contrat verrouillé ici :
 *
 *   1. lecture/écriture dans le schema du tenant CIBLÉ (withinTenant) ;
 *   2. isolation réelle : deux entreprises gardent des réglages distincts ;
 *   3. fail-closed : 403 si la verticale n'est pas activée, 404 entreprise
 *      inconnue, 401/403 sans rôle plateforme adéquat.
 */
class PlatformFundraisingSettingsApiTest extends TestCase
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

    private function actingAsPlatformRole(PlatformRole $role = PlatformRole::Admin): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-fundraising-settings@leopardo.test',
        ]);
        $superAdmin->forceFill([
            'password_hash' => Hash::make('password123'),
            // `platform_role` est hors $fillable (#3597) : forceFill obligatoire.
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    private function endpoint(Company $company): string
    {
        return "/api/v1/platform/companies/{$company->id}/fundraising/settings";
    }

    public function test_show_returns_target_tenant_defaults_without_persisting(): void
    {
        $company = $this->company();
        $this->actingAsPlatformRole();

        $this->getJson($this->endpoint($company))->assertOk()
            ->assertJsonPath('data.enabled_methods', ['card', 'mobile_money', 'bank_transfer', 'cash']);

        // La lecture ne crée pas de ligne (même contrat que la route tenant).
        app(TenantManager::class)->withinTenant($company, function (): void {
            $this->assertSame(0, FundraisingSettings::query()->count());
        });
    }

    public function test_update_writes_in_the_target_tenant_schema(): void
    {
        $company = $this->company();
        $this->actingAsPlatformRole();

        $this->putJson($this->endpoint($company), [
            'enabled_methods' => ['mobile_money'],
            'default_min_amount' => 500,
            'default_max_amount' => 500000,
            'default_suggested_amounts' => [1000, 5000],
            'allow_anonymous' => false,
            'allow_messages' => true,
            'show_supporters' => false,
        ])->assertOk()
            ->assertJsonPath('data.enabled_methods', ['mobile_money'])
            ->assertJsonPath('data.allow_anonymous', false);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            /** @var FundraisingSettings|null $settings */
            $settings = FundraisingSettings::query()->first();
            $this->assertNotNull($settings);
            $this->assertSame(['mobile_money'], $settings->enabled_methods);
            $this->assertFalse($settings->allow_anonymous);
            $this->assertSame($company->id, $settings->company_id);
        });
    }

    public function test_two_companies_keep_isolated_settings(): void
    {
        $first = $this->company();
        $second = $this->company();
        $this->actingAsPlatformRole();

        $this->putJson($this->endpoint($first), [
            'enabled_methods' => ['card'],
            'allow_anonymous' => true,
            'allow_messages' => true,
            'show_supporters' => true,
        ])->assertOk();

        // Le tenant voisin n'a rien reçu : ni ligne, ni valeurs du premier.
        app(TenantManager::class)->withinTenant($second, function (): void {
            $this->assertSame(0, FundraisingSettings::query()->count());
        });

        $this->getJson($this->endpoint($second))->assertOk()
            ->assertJsonPath('data.enabled_methods', ['card', 'mobile_money', 'bank_transfer', 'cash']);
    }

    public function test_fails_closed_when_vertical_not_activated(): void
    {
        $company = $this->company(withFeature: false);
        $this->actingAsPlatformRole();

        $this->getJson($this->endpoint($company))->assertForbidden();
        $this->putJson($this->endpoint($company), [
            'enabled_methods' => ['card'],
        ])->assertForbidden();
    }

    public function test_unknown_company_returns_404(): void
    {
        $this->actingAsPlatformRole();

        $this->getJson('/api/v1/platform/companies/00000000-0000-0000-0000-000000000000/fundraising/settings')
            ->assertNotFound();
    }

    public function test_rbac_is_fail_closed(): void
    {
        $company = $this->company();

        $this->getJson($this->endpoint($company))->assertUnauthorized();

        // `companies.manage` requis : le rôle support (view only) est exclu.
        $this->actingAsPlatformRole(PlatformRole::Support);
        $this->putJson($this->endpoint($company), ['enabled_methods' => ['card']])->assertForbidden();
    }
}
