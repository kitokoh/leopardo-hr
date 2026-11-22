<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Solutions\DemoDataRegistry;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\SeedMinimalEduManagerAction;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduDemoDataKit;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Mission « une école crée son espace » — #9785 et #9786.
 *
 * Verrouille les deux garanties qui rendent l'espace école utilisable dès
 * l'activation :
 *   1. l'amorçage crée le RÉFÉRENTIEL neutre (matières + types de frais) sans
 *      lequel une classe ne peut pas être affectée et une facture émise ;
 *   2. le kit de démonstration installe un établissement complet, IDEMPOTENT
 *      et strictement TENANT-SCOPED.
 */
class EduSchoolBootstrapTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;
    }

    // ─────────────────────────────────────────────────────────────────────
    // #9786 — amorçage élargi
    // ─────────────────────────────────────────────────────────────────────

    public function test_activation_seeds_subjects_and_fee_types(): void
    {
        $this->app->make(SeedMinimalEduManagerAction::class)->execute($this->companyA);

        $this->assertGreaterThanOrEqual(
            6,
            EduSubject::query()->where('company_id', $this->companyA->id)->count(),
            'Le socle de matières doit être créé à l\'activation.'
        );

        $this->assertGreaterThanOrEqual(
            4,
            EduFeeType::query()->where('company_id', $this->companyA->id)->count(),
            'Les types de frais courants doivent être créés à l\'activation.'
        );

        // Codes stables : ils servent de clé d'idempotence et de référence.
        $this->assertDatabaseHas('edu_subjects', [
            'company_id' => $this->companyA->id,
            'code' => 'MATH',
        ]);
        $this->assertDatabaseHas('edu_fee_types', [
            'company_id' => $this->companyA->id,
            'code' => 'TUITION',
            'billing_frequency' => EduFeeType::FREQUENCY_TERM,
        ]);

        // Devise du tenant, montant neutre (le directeur ajuste).
        $tuition = EduFeeType::query()
            ->where('company_id', $this->companyA->id)
            ->where('code', 'TUITION')
            ->firstOrFail();
        $this->assertSame('DZD', $tuition->currency);
        $this->assertSame('0.00', (string) $tuition->amount);
    }

    public function test_activation_is_idempotent(): void
    {
        $action = $this->app->make(SeedMinimalEduManagerAction::class);

        $action->execute($this->companyA);
        $subjectsAfterFirst = EduSubject::query()->where('company_id', $this->companyA->id)->count();
        $feesAfterFirst = EduFeeType::query()->where('company_id', $this->companyA->id)->count();
        $campusesAfterFirst = EduCampus::query()->where('company_id', $this->companyA->id)->count();

        $action->execute($this->companyA);

        $this->assertSame(
            $subjectsAfterFirst,
            EduSubject::query()->where('company_id', $this->companyA->id)->count(),
            'Rejouer l\'amorçage ne doit créer aucune matière en double.'
        );
        $this->assertSame(
            $feesAfterFirst,
            EduFeeType::query()->where('company_id', $this->companyA->id)->count(),
            'Rejouer l\'amorçage ne doit créer aucun type de frais en double.'
        );
        $this->assertSame(
            $campusesAfterFirst,
            EduCampus::query()->where('company_id', $this->companyA->id)->count(),
            'Rejouer l\'amorçage ne doit créer aucun campus en double.'
        );
    }

    public function test_activation_does_not_leak_across_tenants(): void
    {
        $this->app->make(SeedMinimalEduManagerAction::class)->execute($this->companyA);

        $this->assertSame(
            0,
            EduSubject::query()->where('company_id', $this->companyB->id)->count(),
            'L\'amorçage d\'un tenant ne doit rien écrire chez un autre.'
        );
        $this->assertSame(
            0,
            EduFeeType::query()->where('company_id', $this->companyB->id)->count()
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // #9785 — kit de démonstration
    // ─────────────────────────────────────────────────────────────────────

    public function test_demo_kit_is_registered_for_edumanager(): void
    {
        /** @var DemoDataRegistry $registry */
        $registry = $this->app->make(DemoDataRegistry::class);

        $this->assertTrue(
            $registry->has('edumanager'),
            'La verticale edumanager doit exposer un kit de démonstration.'
        );

        // Le registre est fail-closed : un code inconnu n'est jamais résolu.
        $this->assertFalse($registry->has('inconnu'));

        // Le kit est bien une implémentation du contrat partagé.
        $this->assertInstanceOf(
            DemoDataKit::class,
            $this->app->make(EduDemoDataKit::class)
        );
    }

    public function test_demo_kit_installs_a_complete_school(): void
    {
        $this->app->make(EduDemoDataKit::class)->seed($this->companyA);

        $this->assertGreaterThanOrEqual(
            3,
            EduClass::query()->where('company_id', $this->companyA->id)->count(),
            'Le kit doit créer des classes de démonstration.'
        );
        $this->assertGreaterThanOrEqual(
            6,
            EduStudent::query()->where('company_id', $this->companyA->id)->count(),
            'Le kit doit créer des élèves fictifs.'
        );
        $this->assertGreaterThanOrEqual(
            3,
            EduGuardian::query()->where('company_id', $this->companyA->id)->count(),
            'Le kit doit créer des responsables légaux fictifs.'
        );
        $this->assertGreaterThanOrEqual(
            6,
            EduClassEnrollment::query()->where('company_id', $this->companyA->id)->count(),
            'Chaque élève de démonstration doit être inscrit dans sa classe.'
        );
        $this->assertGreaterThanOrEqual(
            6,
            EduStudentGuardian::query()->where('company_id', $this->companyA->id)->count(),
            'Chaque élève de démonstration doit être rattaché à un responsable.'
        );

        // Le référentiel neutre est installé par le kit, jamais dupliqué.
        $this->assertGreaterThanOrEqual(
            6,
            EduSubject::query()->where('company_id', $this->companyA->id)->count()
        );
    }

    public function test_demo_kit_is_idempotent(): void
    {
        $kit = $this->app->make(EduDemoDataKit::class);

        $kit->seed($this->companyA);

        $snapshot = [
            'classes' => EduClass::query()->where('company_id', $this->companyA->id)->count(),
            'students' => EduStudent::query()->where('company_id', $this->companyA->id)->count(),
            'guardians' => EduGuardian::query()->where('company_id', $this->companyA->id)->count(),
            'enrollments' => EduClassEnrollment::query()->where('company_id', $this->companyA->id)->count(),
            'links' => EduStudentGuardian::query()->where('company_id', $this->companyA->id)->count(),
        ];

        $kit->seed($this->companyA);

        $this->assertSame($snapshot['classes'], EduClass::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame($snapshot['students'], EduStudent::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame($snapshot['guardians'], EduGuardian::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame($snapshot['enrollments'], EduClassEnrollment::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame($snapshot['links'], EduStudentGuardian::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_demo_kit_is_tenant_scoped(): void
    {
        $this->app->make(EduDemoDataKit::class)->seed($this->companyA);

        $this->assertSame(
            0,
            EduStudent::query()->where('company_id', $this->companyB->id)->count(),
            'Le kit ne doit jamais écrire chez un autre tenant.'
        );
        $this->assertSame(
            0,
            EduClass::query()->where('company_id', $this->companyB->id)->count()
        );
        $this->assertSame(
            0,
            EduGuardian::query()->where('company_id', $this->companyB->id)->count()
        );
    }

    public function test_demo_kit_uses_only_fictional_names(): void
    {
        $this->app->make(EduDemoDataKit::class)->seed($this->companyA);

        $students = EduStudent::query()->where('company_id', $this->companyA->id)->get();

        foreach ($students as $student) {
            $this->assertStringStartsWith(
                'DEMO-',
                (string) $student->student_number,
                'Les élèves de démonstration doivent être identifiables par leur numéro.'
            );
        }
    }
}
