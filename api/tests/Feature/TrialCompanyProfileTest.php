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

        // #7423 — PLANCHER D’ACCÈS. Les outils d'ÉQUIPE restent coupés pour un
        // indépendant, mais le socle RH INDIVIDUEL est désormais GARANTI : se
        // pointer, poser ses congés, lire ses bulletins (`SOLO_FLOOR_TOOLS`).
        // Avant #7423, la totalité des clés RH était coupée et un solo se
        // retrouvait sans aucun outil RH.
        foreach (Company::TEAM_TOOLS as $tool) {
            $isFloor = in_array($tool, Company::SOLO_FLOOR_TOOLS, true);

            $this->assertSame(
                $isFloor,
                $selection[$tool],
                $isFloor
                    ? "Le socle RH « {$tool} » doit rester OUVERT pour un profil solo (#7423)."
                    : "L'outil d'équipe « {$tool} » doit être désactivé pour un profil solo.",
            );
        }

        // Miroir vers le registre des feature flags plateforme.
        $this->assertTrue($company->hasFeature('accounting'));
        $this->assertFalse($company->hasFeature('crm'));
    }

    /**
     * #7423 — Le plancher est appliqué à la LECTURE de `moduleSelection()`, donc
     * un tenant solo **déjà provisionné** en bénéficie — sans re-provisioning.
     *
     * C'est le point qui compte : la génération précédente du provisioning
     * écrivait `false` pour TOUS les outils d'équipe d'un solo (y compris
     * `attendance`, `absences`, `payroll`). Corriger seulement le provisioning
     * n'aurait servi qu'aux nouveaux tenants ; les tenants existants seraient
     * restés avec un socle RH vide pour toujours.
     */
    public function test_solo_floor_is_guaranteed_at_read_time_for_existing_tenants(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [
                'company_type' => Company::TYPE_SOLO,
                'modules' => [
                    'employees' => false,
                    'attendance' => false,
                    'absences' => false,
                    'payroll' => false,
                    'contracts' => false,
                    'training' => false,
                    'accounting' => true,
                ],
            ],
        ]);

        $this->assertTrue($company->isSolo());

        $selection = $company->moduleSelection();
        $this->assertIsArray($selection);

        // Le plancher est garanti, malgré le `false` persisté.
        foreach (Company::SOLO_FLOOR_TOOLS as $tool) {
            $this->assertTrue(
                $selection[$tool] ?? false,
                "Le socle RH « {$tool} » doit être garanti à la lecture (#7423).",
            );
        }

        // Les outils d'ÉQUIPE restent coupés, et la sélection réelle est intacte.
        $this->assertFalse($selection['employees']);
        $this->assertFalse($selection['contracts']);
        $this->assertFalse($selection['training']);
        $this->assertTrue($selection['accounting']);
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
