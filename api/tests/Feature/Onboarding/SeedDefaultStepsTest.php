<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4188 — SeedDefaultSteps doit écrire les colonnes réelles du modèle
 * (`step_key`/`title` et non `key`/`label`, non fillable) : sans cela chaque
 * étape est insérée avec step_key/title NULL et la dédup (pluck('key')) ne
 * fonctionne pas → onboarding vide + doublons au re-provisioning.
 */
class SeedDefaultStepsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_seed_creates_steps_with_non_null_step_key_and_title(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        (new SeedDefaultSteps)->execute((string) $company->id);

        $steps = OnboardingStep::where('company_id', $company->id)->orderBy('order')->get();

        // #4929 : contrat unifié — 10 étapes (source de vérité unique du seeder).
        $this->assertCount(10, $steps);
        $this->assertSame(
            ['company_info', 'first_department', 'first_employee', 'first_attendance', 'invite_manager',
             'configure_schedules', 'first_report', 'configure_payroll', 'install_kiosk', 'activate_geofence'],
            $steps->pluck('step_key')->all()
        );
        /** @var OnboardingStep $firstStep */
        $firstStep = $steps->first();
        $this->assertSame('Renseigner les informations entreprise', $firstStep->title);
        // Aucune étape ne doit avoir step_key/title NULL (régression #4188).
        $this->assertNotContains(null, $steps->pluck('step_key')->all());
        $this->assertNotContains(null, $steps->pluck('title')->all());
    }

    public function test_seed_is_idempotent_on_second_call(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        (new SeedDefaultSteps)->execute((string) $company->id);
        (new SeedDefaultSteps)->execute((string) $company->id);

        $this->assertSame(10, OnboardingStep::where('company_id', $company->id)->count());
    }

    public function test_seed_does_not_duplicate_when_a_step_was_manually_completed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        (new SeedDefaultSteps)->execute((string) $company->id);

        // L'utilisateur marque une étape complétée puis un re-provisioning
        // survient : aucune ligne dupliquée pour cette étape.
        OnboardingStep::where('company_id', $company->id)
            ->where('step_key', 'configure_payroll')
            ->update(['status' => 'completed', 'completed_at' => now()]);

        (new SeedDefaultSteps)->execute((string) $company->id);

        $this->assertSame(10, OnboardingStep::where('company_id', $company->id)->count());
        $this->assertSame(
            1,
            OnboardingStep::where('company_id', $company->id)->where('step_key', 'configure_payroll')->count(),
        );
    }
}
