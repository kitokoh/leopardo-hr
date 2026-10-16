<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Application\Actions\ProvisionFarmOs;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — Étape 4 : contrat de PROVISIONNEMENT (idempotence, récupérabilité,
 * non-destruction, modularité par activité, isolation multi-tenant).
 *
 * Ces tests exercent l'action RÉELLE `ProvisionFarmOs` sur un schéma tenant.
 * Ils ne simulent ni le catalogue de solutions ni l'activation : le pack Farm
 * OS n'ayant qu'une dépendance REQUISE (`rh`, un drapeau du socle déjà actif
 * par défaut), `activateWithDependencies` est réellement exécuté.
 */
final class FarmProvisioningTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        // Contexte tenant : `BelongsToCompany` force `company_id` depuis le
        // tenant courant (durcissement #7646) et `FarmCompanyContext` le résout.
        app()->instance('current_company', $this->company);
    }

    public function test_provisioning_activates_solution_and_persists_activities(): void
    {
        $result = $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        $this->assertContains($result['status'], ['activated', 'already_active']);
        $this->assertTrue($this->company->fresh()->hasFeature('farm_os'));

        $this->assertDatabaseHas('farm_activities', [
            'company_id' => $this->company->id,
            'activity' => FarmActivity::CerealCrops->value,
            'is_primary' => true,
        ]);
    }

    public function test_provisioning_is_idempotent(): void
    {
        $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        $firstActivityCount = $this->countForCompany('farm_activities');
        $firstDecisionCount = $this->countForCompany('farm_capabilities');

        $second = $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        // Rejeu : statut « déjà active », aucune ligne dupliquée.
        $this->assertSame('already_active', $second['status']);
        $this->assertSame($firstActivityCount, $this->countForCompany('farm_activities'));
        $this->assertSame($firstDecisionCount, $this->countForCompany('farm_capabilities'));
    }

    public function test_a_crop_only_farm_never_receives_livestock(): void
    {
        $result = $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        // L'élevage est explicitement REFUSÉ : jamais un activateur implicite.
        $this->assertFalse($result['capabilities'][FarmCapability::Livestock->value] ?? false);
        $this->assertNotContains(FarmCapability::Livestock->value, $result['enabled']);
        $this->assertContains(FarmCapability::ParcelsCrops->value, $result['enabled']);
    }

    public function test_a_livestock_only_farm_never_receives_irrigation(): void
    {
        $result = $this->provision($this->company, [FarmActivity::Cattle->value]);

        $this->assertContains(FarmCapability::Livestock->value, $result['enabled']);
        $this->assertNotContains(FarmCapability::Irrigation->value, $result['enabled']);
    }

    public function test_explicit_user_decisions_win_over_recommendation(): void
    {
        // L'utilisateur REFUSE un outil pourtant recommandé : le refus est
        // persisté et respecté, Farm OS ne le réactive pas.
        $result = $this->provision(
            $this->company,
            [FarmActivity::CerealCrops->value],
            [FarmCapability::ParcelsCrops->value => false],
        );

        $this->assertNotContains(FarmCapability::ParcelsCrops->value, $result['enabled']);
        $this->assertFalse(FarmCapabilitySetting::decisions()[FarmCapability::ParcelsCrops->value]);
    }

    public function test_a_declined_tool_stays_declined_after_a_second_provisioning(): void
    {
        // RÉGRESSION : une décision explicite ne doit pas être écrasée par le
        // rejeu (idempotence ≠ réinitialisation).
        $decision = [FarmCapability::ParcelsCrops->value => false];

        $this->provision($this->company, [FarmActivity::CerealCrops->value], $decision);
        $this->provision($this->company, [FarmActivity::CerealCrops->value], $decision);

        $this->assertFalse(FarmCapabilitySetting::decisions()[FarmCapability::ParcelsCrops->value]);
    }

    public function test_checklist_is_honest_before_any_business_data_exists(): void
    {
        $result = $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        $checklist = collect($result['checklist'])->keyBy('key');

        // Le provisioning ne prétend jamais avoir créé une exploitation ou une
        // parcelle : ces étapes restent explicitement à faire.
        $this->assertTrue($checklist['farm_solution_active']['done']);
        $this->assertTrue($checklist['farm_activity_declared']['done']);
        $this->assertFalse($checklist['farm_first_site']['done']);
        $this->assertFalse($checklist['farm_first_parcel']['done']);
        $this->assertFalse($checklist['farm_first_operation']['done']);
    }

    public function test_next_actions_are_limited_to_activated_tools(): void
    {
        // Profil exclusivement végétal : l'action « cheptel » n'est jamais
        // proposée — aucun outil hors du périmètre réel de l'exploitation.
        $result = $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        $keys = array_column($result['next_actions'], 'key');

        $this->assertContains('create_first_parcel', $keys);
        $this->assertNotContains('register_livestock_batch', $keys);
    }

    public function test_provisioning_is_isolated_to_the_current_tenant(): void
    {
        // Isolation multi-tenant : provisionner l'exploitation A ne crée
        // AUCUNE donnée pour l'exploitation B.
        $other = Company::factory()->create();

        $this->provision($this->company, [FarmActivity::CerealCrops->value]);

        $this->assertSame(0, DB::table('farm_activities')->where('company_id', $other->id)->count());
        $this->assertSame(0, DB::table('farm_capabilities')->where('company_id', $other->id)->count());
        $this->assertFalse($other->fresh()->hasFeature('farm_os'));
    }

    /**
     * @param  list<string>  $activities
     * @param  array<string, bool>|null  $decisions
     * @return array<string, mixed>
     */
    private function provision(Company $company, array $activities, ?array $decisions = null): array
    {
        return app(ProvisionFarmOs::class)->execute($company, $activities, $decisions, null);
    }

    private function countForCompany(string $table): int
    {
        return DB::table($table)->where('company_id', $this->company->id)->count();
    }
}
