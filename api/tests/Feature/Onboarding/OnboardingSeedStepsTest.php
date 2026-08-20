<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4188 : SeedDefaultSteps écrivait 'key'/'label' (non fillable,
 * 'label' non-colonne) → step_key/title NULL + dédup par pluck('key') cassée.
 */
class OnboardingSeedStepsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_seed_default_steps_persists_step_key_and_title(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        (new SeedDefaultSteps())->execute((string) $company->id);

        $steps = OnboardingStep::where('company_id', $company->id)->get();

        // #4929 : contrat unifié — 10 étapes (source de vérité unique du seeder).
        $this->assertCount(10, $steps);
        foreach ($steps as $step) {
            $this->assertNotSame('', $step->step_key, 'step_key ne doit pas être NULL');
            $this->assertNotSame('', $step->title, 'title ne doit pas être NULL');
            $this->assertSame('pending', $step->status);
        }

        $this->assertSame(
            ['activate_geofence', 'company_info', 'configure_payroll', 'configure_schedules', 'first_attendance',
             'first_department', 'first_employee', 'first_report', 'install_kiosk', 'invite_manager'],
            $steps->pluck('step_key')->sort()->values()->all()
        );
    }

    public function test_seed_default_steps_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $action = new SeedDefaultSteps();

        $action->execute((string) $company->id);
        $action->execute((string) $company->id);

        $this->assertSame(
            10,
            OnboardingStep::where('company_id', $company->id)->count(),
            'un second appel ne doit pas dupliquer les étapes'
        );
    }
}
