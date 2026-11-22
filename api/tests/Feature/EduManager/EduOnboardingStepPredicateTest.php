<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduOnboardingStepPredicate;
use App\Modules\Onboarding\Application\Services\StepCompletionGuard;
use App\Shared\Onboarding\StepPredicateRegistry;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9787 — les étapes d'onboarding école ne sont plus déclaratives.
 *
 * Avant : `StepCompletionGuard::isSatisfied()` renvoyait `null` pour
 * `edu_setup_campus`, `edu_setup_classes` et `edu_first_students` — le bouton
 * « Suivant » les marquait terminées sans qu'aucune action réelle n'ait eu
 * lieu, ce qui faussait `progress_percent` et `go_live_ready` (même défaut que
 * #7261 pour `first_employee`).
 *
 * Après : le module EduManager déclare un prédicat dans le registre partagé
 * (inversion de dépendance — `Modules/Onboarding` n'importe jamais
 * `Modules/EduManager`, garde #5584) et le guard l'évalue en premier.
 */
class EduOnboardingStepPredicateTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->company = $company;
    }

    public function test_predicate_is_registered_in_the_shared_registry(): void
    {
        /** @var StepPredicateRegistry $registry */
        $registry = $this->app->make(StepPredicateRegistry::class);

        $this->assertGreaterThan(0, $registry->count());

        $predicate = $registry->resolve(EduOnboardingStepPredicate::STEP_CAMPUS);
        $this->assertInstanceOf(EduOnboardingStepPredicate::class, $predicate);

        // Une étape d'une autre verticale n'est pas capturée par ce prédicat.
        $this->assertNull($registry->resolve('first_employee'));
    }

    public function test_campus_step_requires_a_real_campus(): void
    {
        /** @var StepCompletionGuard $guard */
        $guard = $this->app->make(StepCompletionGuard::class);

        // Aucun campus : l'étape n'est PAS satisfaite (et non `null`).
        $this->assertFalse(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_CAMPUS)
        );

        EduCampus::query()->create([
            'company_id' => $this->company->id,
            'code' => 'MAIN',
            'name' => 'Campus principal',
            'status' => EduCampus::STATUS_ACTIVE,
            'timezone' => 'UTC',
        ]);

        $this->assertTrue(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_CAMPUS)
        );
    }

    public function test_classes_step_requires_a_real_class(): void
    {
        /** @var StepCompletionGuard $guard */
        $guard = $this->app->make(StepCompletionGuard::class);

        $this->assertFalse(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_CLASSES)
        );

        $campus = EduCampus::query()->create([
            'company_id' => $this->company->id,
            'code' => 'MAIN',
            'name' => 'Campus principal',
            'status' => EduCampus::STATUS_ACTIVE,
            'timezone' => 'UTC',
        ]);

        $year = EduAcademicYear::query()->create([
            'company_id' => $this->company->id,
            'name' => '2026-2027',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(8)->toDateString(),
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        EduClass::query()->create([
            'company_id' => $this->company->id,
            'campus_id' => $campus->id,
            'academic_year_id' => $year->id,
            'name' => '6e A',
            'status' => EduClass::STATUS_ACTIVE,
        ]);

        $this->assertTrue(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_CLASSES)
        );
    }

    public function test_students_step_requires_a_real_student(): void
    {
        /** @var StepCompletionGuard $guard */
        $guard = $this->app->make(StepCompletionGuard::class);

        $this->assertFalse(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_STUDENTS)
        );

        EduStudent::query()->create([
            'company_id' => $this->company->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Élève Test',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $this->assertTrue(
            $guard->isSatisfied($this->company->id, EduOnboardingStepPredicate::STEP_STUDENTS)
        );
    }

    public function test_predicate_is_tenant_scoped(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);

        EduCampus::query()->create([
            'company_id' => $this->company->id,
            'code' => 'MAIN',
            'name' => 'Campus principal',
            'status' => EduCampus::STATUS_ACTIVE,
            'timezone' => 'UTC',
        ]);

        /** @var StepCompletionGuard $guard */
        $guard = $this->app->make(StepCompletionGuard::class);

        // Le campus du tenant A ne valide PAS l'étape du tenant B.
        $this->assertFalse(
            $guard->isSatisfied($other->id, EduOnboardingStepPredicate::STEP_CAMPUS)
        );
    }

    public function test_non_edu_steps_keep_their_previous_behaviour(): void
    {
        /** @var StepCompletionGuard $guard */
        $guard = $this->app->make(StepCompletionGuard::class);

        // Une étape sans prédicat reste déclarative (`null`) : aucun module ne
        // s'en déclare responsable, le client n'est pas bloqué.
        $this->assertNull($guard->isSatisfied($this->company->id, 'some_declarative_step'));
    }
}
