<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Actions\ProvisionGuidedTrial;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7235 — Profil d'entreprise à l'inscription.
 *
 *  - `company_type` (`company` | `solo`) et `modules[]` (outils horizontaux)
 *    sont validés fail-closed contre `Company::HORIZONTAL_TOOLS` ;
 *  - un profil `solo` désactive EXPLICITEMENT les outils d'équipe
 *    (`Company::TEAM_TOOLS`) : la règle est serveur, pas cliente ;
 *  - la sélection est persistée dans `metadata.modules` et re-exposable par
 *    `/auth/me` (EmployeeResource), et les clés qui sont aussi des feature
 *    flags plateforme (`accounting`, `crm`) sont miroirées dans `features` ;
 *  - une inscription SANS sélection (hero vitrine, appelants historiques)
 *    n'écrit pas `metadata.modules` ⇒ aucun verrouillage rétroactif.
 */
class TrialCompanyProfileTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    public function test_guided_trial_persists_solo_profile_without_team_tools(): void
    {
        Mail::fake();
        Queue::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'solo@newtech.dz',
            'company' => 'Solo Compta',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
            'company_type' => 'solo',
            'modules' => ['accounting', 'reports'],
        ])->assertStatus(200);

        Queue::assertPushed(
            ProvisionDemoTenantJob::class,
            fn (ProvisionDemoTenantJob $job): bool => $job->companyType === 'solo'
                && $job->modules === ['accounting', 'reports']
        );

        $result = app(ProvisionGuidedTrial::class)->execute(
            'solo@newtech.dz',
            'Solo Compta',
            'DZ',
            [],
            'solo',
            ['accounting', 'reports'],
        );

        /** @var Company $company */
        $company = $result['company'];

        $this->assertSame(Company::TYPE_SOLO, $company->companyType());
        $this->assertTrue($company->isSolo());

        $selection = $company->moduleSelection();
        $this->assertIsArray($selection);
        $this->assertTrue($selection['accounting']);
        $this->assertTrue($selection['reports']);
        $this->assertFalse($selection['marketing']);

        // Aucun outil d'équipe pour un indépendant.
        foreach (Company::TEAM_TOOLS as $tool) {
            $this->assertFalse($selection[$tool], "L'outil {$tool} doit être désactivé pour un profil solo.");
        }

        // Miroir vers le registre des feature flags plateforme.
        $this->assertTrue($company->hasFeature('accounting'));
        $this->assertFalse($company->hasFeature('crm'));
    }

    public function test_company_profile_keeps_selected_tools_and_persists_vertical(): void
    {
        Mail::fake();

        $result = app(ProvisionGuidedTrial::class)->execute(
            'resto@newtech.dz',
            'Resto SARL',
            'DZ',
            ['restaurant'],
            'company',
            ['employees', 'attendance', 'absences', 'accounting'],
        );

        /** @var Company $company */
        $company = $result['company'];
        $selection = $company->moduleSelection();

        $this->assertIsArray($selection);
        $this->assertTrue($selection['employees']);
        $this->assertTrue($selection['attendance']);
        $this->assertTrue($selection['absences']);
        $this->assertTrue($selection['accounting']);
        $this->assertFalse($selection['payroll']);
        $this->assertFalse($selection['crm']);
        $this->assertSame('restaurant', $company->metadata['vertical'] ?? null);
        $this->assertSame(Company::TYPE_COMPANY, $company->companyType());
        $this->assertTrue($company->hasFeature('restaurant'));
    }

    public function test_legacy_signup_without_profile_keeps_no_explicit_selection(): void
    {
        Mail::fake();

        $result = app(ProvisionGuidedTrial::class)->execute('legacy@newtech.dz', 'Legacy Co', 'DZ');

        /** @var Company $company */
        $company = $result['company'];

        $this->assertSame(Company::TYPE_COMPANY, $company->companyType());
        $this->assertNull($company->moduleSelection());
        $this->assertArrayNotHasKey('modules', $company->metadata ?? []);
    }

    public function test_signup_rejects_unknown_horizontal_tool_fail_closed(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'bad@newtech.dz',
            'company' => 'Bad Tools Co',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
            'modules' => ['not_a_real_tool'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['modules.0']);
    }

    public function test_signup_rejects_unknown_company_type_fail_closed(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'bad-type@newtech.dz',
            'company' => 'Bad Type Co',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
            'company_type' => 'freelance',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_type']);
    }
}
