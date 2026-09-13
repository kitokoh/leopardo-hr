<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7300 — LES TROIS SURFACES D'ONBOARDING DOIVENT CONCORDER.
 *
 * Constat d'origine : pour un même tenant, au même instant,
 *   - l'assistant client      (/onboarding-setup/checklist) affichait 100 %,
 *   - le moteur calculé       (/onboarding/checklist)      affichait  38 %,
 *   - le back-office admin    (/platform/.../health)       affichait  40 %.
 *
 * Décision (documentée dans
 * `docs/dossierdeConception/11_ux_wireframes/24_ONBOARDING_GUIDE.md`) : la table
 * `onboarding_steps` — donc `/onboarding-setup/checklist` — est la SOURCE DE
 * VÉRITÉ ; les deux autres surfaces la relaient au lieu de recalculer la leur.
 * Ces tests verrouillent cette propriété : toute réintroduction d'une échelle
 * parallèle casse ici.
 */
class OnboardingProgressAlignmentTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{company: Company, manager: Employee}
     */
    private function companyWithSteps(string $status): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);
        app(SeedDefaultSteps::class)->execute($company->id);
        app()->forgetInstance('current_company');

        OnboardingStep::query()
            ->where('company_id', $company->id)
            ->update(['status' => $status]);

        return ['company' => $company, 'manager' => $manager];
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

    public function test_the_three_surfaces_agree_when_setup_is_complete(): void
    {
        $fixture = $this->companyWithSteps('completed');
        $company = $fixture['company'];

        // ── A. Source de vérité (assistant client)
        Sanctum::actingAs($fixture['manager']);
        $setup = $this->getJson('/api/v1/onboarding-setup/checklist')->assertOk()->json('data');

        // ── B. Moteur calculé (déprécié)
        $calculated = $this->getJson('/api/v1/onboarding/checklist')->assertOk()->json('data');

        // ── C. Back-office plateforme
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');
        $health = $this->getJson("/api/v1/platform/companies/{$company->id}/health")
            ->assertOk()
            ->json('data.adoption.onboarding');

        // La source de vérité est bien « terminé »
        $this->assertTrue($setup['go_live_ready'], 'La checklist setup doit être go-live ready.');
        $this->assertSame($setup['total_steps'], $setup['completed_steps']);

        // B relaie la progression canonique (plus de 38 % fantôme)
        foreach (['completed_steps', 'total_steps', 'progress_percent', 'go_live_ready'] as $key) {
            $this->assertSame(
                $setup[$key],
                $calculated[$key],
                "Le moteur calculé diverge de la source de vérité sur « {$key} ».",
            );
        }

        // C relaie la progression canonique (plus de 40 % fantôme)
        $this->assertSame($setup['completed_steps'], $health['completed_steps']);
        $this->assertSame($setup['total_steps'], $health['total_steps']);
        $this->assertSame($setup['progress_percent'], $health['progress_percent']);
        $this->assertTrue($health['go_live_ready']);
        $this->assertSame('onboarding_steps', $health['source']);

        // Les deux autres surfaces annoncent explicitement leur statut
        $this->assertTrue($calculated['deprecated']);
        $this->assertSame('/onboarding-setup/checklist', $calculated['canonical_source']);
    }

    public function test_the_three_surfaces_agree_when_setup_is_pending(): void
    {
        // Non-régression : la divergence historique se produisait AUSSI sur un
        // tenant inachevé (le client comptait les étapes sautées, le moteur
        // calculé les prédicats). L'égalité doit tenir dans les deux sens.
        $fixture = $this->companyWithSteps('pending');
        $company = $fixture['company'];

        Sanctum::actingAs($fixture['manager']);
        $setup = $this->getJson('/api/v1/onboarding-setup/checklist')->assertOk()->json('data');
        $calculated = $this->getJson('/api/v1/onboarding/checklist')->assertOk()->json('data');

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');
        $health = $this->getJson("/api/v1/platform/companies/{$company->id}/health")
            ->assertOk()
            ->json('data.adoption.onboarding');

        $this->assertFalse($setup['go_live_ready']);
        $this->assertSame(0, $setup['progress_percent']);

        $this->assertSame($setup['progress_percent'], $calculated['progress_percent']);
        $this->assertSame($setup['progress_percent'], $health['progress_percent']);
        $this->assertFalse($calculated['go_live_ready']);
        $this->assertFalse($health['go_live_ready']);
    }

    public function test_the_deprecated_engine_still_serves_its_observed_facts(): void
    {
        // Le wizard dépend de `steps` (badge Quick Start + auto-complétion) :
        // ce contrat ne doit pas être cassé par l'alignement des progressions.
        $fixture = $this->companyWithSteps('pending');

        Sanctum::actingAs($fixture['manager']);
        $calculated = $this->getJson('/api/v1/onboarding/checklist')->assertOk()->json('data');

        $this->assertCount(8, $calculated['steps']);
        $this->assertSame('company_created', $calculated['steps'][0]['key']);

        // Les faits observés restent exposés — sous un nom qui ne prétend plus
        // être la progression d'onboarding.
        $this->assertArrayHasKey('observed', $calculated);
        $this->assertArrayHasKey('completed_steps', $calculated['observed']);
        $this->assertArrayHasKey('total_steps', $calculated['observed']);
        $this->assertArrayHasKey('progress_percent', $calculated['observed']);
        $this->assertSame(8, $calculated['observed']['total_steps']);
    }
}
