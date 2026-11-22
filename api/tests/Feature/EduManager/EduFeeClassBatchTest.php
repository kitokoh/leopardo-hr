<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9792 — encaissement en lot DEPUIS LA FICHE D'UNE CLASSE, et filtres du lot.
 *
 * Le guichet part de l'effectif : à la rentrée, c'est la classe qui règle. Cet
 * endpoint rend l'effectif actif avec, par élève, ses charges encore
 * encaissables et leur solde — l'écran n'a plus qu'à pré-sélectionner.
 *
 * Ce que ces tests verrouillent :
 *
 * 1. **Le périmètre est la CLASSE, pas le tenant.** Un élève d'une autre classe
 *    ne doit jamais apparaître : sinon le caissier encaisserait des familles
 *    qui ne sont pas dans la classe qu'il a ouverte.
 * 2. **Un élève sans charge reste visible** (`charges: []`) — le masquer
 *    ferait croire à un effectif incomplet.
 * 3. **Les charges terminales sont exclues par défaut** : elles seraient
 *    refusées par le lot, les proposer ferait échouer la saisie entière.
 * 4. **Les filtres de période et de moyen de paiement** restreignent la
 *    sélection, et le solde reste calculé côté serveur.
 */
class EduFeeClassBatchTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduAcademicYear $yearA;

    private EduCampus $campusA;

    private EduClass $classA;

    private EduClass $classB;

    /** @var list<EduStudent> */
    private array $studentsA = [];

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $companyA->id,
            'code' => 'C1',
            'name' => 'Campus principal',
            'status' => 'active',
        ]);
        $this->campusA = $campusA;

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'campus_id' => $campusA->id,
            'academic_year_id' => $yearA->id,
            'code' => '6A',
            'name' => '6ème A',
            'status' => EduClass::STATUS_ACTIVE,
        ]);
        $this->classA = $classA;

        /** @var EduClass $classB */
        $classB = EduClass::query()->create([
            'company_id' => $companyA->id,
            'campus_id' => $campusA->id,
            'academic_year_id' => $yearA->id,
            'code' => '6B',
            'name' => '6ème B',
            'status' => EduClass::STATUS_ACTIVE,
        ]);
        $this->classB = $classB;

        foreach (['Lina Benali', 'Yacine Haddad', 'Nour Cherif'] as $i => $name) {
            /** @var EduStudent $student */
            $student = EduStudent::query()->create([
                'company_id' => $companyA->id,
                'student_number' => 'STU-000'.($i + 1),
                'display_name' => $name,
                'status' => EduStudent::STATUS_ACTIVE,
            ]);
            $this->studentsA[] = $student;

            // Les deux premiers sont dans 6A, le troisième dans 6B.
            EduClassEnrollment::query()->create([
                'company_id' => $companyA->id,
                'class_id' => $i < 2 ? $classA->id : $classB->id,
                'student_id' => $student->id,
                'academic_year_id' => $yearA->id,
                'enrolled_at' => now(),
                'status' => EduClassEnrollment::STATUS_ACTIVE,
            ]);
        }
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    private function createFeeType(): int
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson($this->baseUrl().'/fee-types', [
            'code' => 'TUITION',
            'label' => 'Scolarité annuelle',
            'amount' => 50000,
            'currency' => 'DZD',
            'billing_frequency' => 'once',
        ]);

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    private function createCharge(int $feeTypeId, int $studentIndex, string $externalId, ?string $dueDate = null): int
    {
        Sanctum::actingAs($this->principalA);

        $payload = [
            'student_id' => (int) $this->studentsA[$studentIndex]->getAttribute('id'),
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'external_id' => $externalId,
        ];

        if ($dueDate !== null) {
            $payload['due_date'] = $dueDate;
        }

        $response = $this->postJson($this->baseUrl().'/fee-charges', $payload);
        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    /**
     * L'effectif de la classe, avec les charges de chaque élève et leur solde.
     */
    public function test_class_roster_carries_charges_and_balances(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-class-1');
        $this->createCharge($feeTypeId, 1, 'ext-class-2');
        // Élève de 6B : ne doit PAS apparaître dans l'effectif de 6A.
        $this->createCharge($feeTypeId, 2, 'ext-class-3');

        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$c1}/payments", [
            'amount' => 20000,
            'method' => 'cash',
            'external_id' => 'ext-class-pay-1',
        ])->assertStatus(201);

        $response = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges');
        $response->assertOk();

        $students = $response->json('data.students');
        $this->assertCount(2, $students, 'Seuls les élèves de 6A doivent apparaître.');

        $names = array_column($students, 'display_name');
        $this->assertContains('Lina Benali', $names);
        $this->assertContains('Yacine Haddad', $names);
        $this->assertNotContains('Nour Cherif', $names, 'Un élève de 6B ne doit pas fuiter.');

        $lina = null;
        foreach ($students as $student) {
            if ($student['display_name'] === 'Lina Benali') {
                $lina = $student;
            }
        }

        $this->assertNotNull($lina);
        $this->assertCount(1, $lina['charges']);
        $this->assertSame(50000, $lina['charges'][0]['amount']);
        $this->assertSame(20000, $lina['charges'][0]['paid_amount']);
        $this->assertSame(30000, $lina['charges'][0]['remaining_amount']);
    }

    /**
     * Un élève sans charge reste dans l'effectif, avec une liste vide.
     */
    public function test_student_without_charge_stays_in_roster(): void
    {
        $feeTypeId = $this->createFeeType();
        $this->createCharge($feeTypeId, 0, 'ext-empty-1');

        Sanctum::actingAs($this->principalA);
        $response = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges');
        $response->assertOk();

        $students = $response->json('data.students');
        $this->assertCount(2, $students);

        $yacine = null;
        foreach ($students as $student) {
            if ($student['display_name'] === 'Yacine Haddad') {
                $yacine = $student;
            }
        }

        $this->assertNotNull($yacine, 'Un élève sans charge doit rester visible.');
        $this->assertSame([], $yacine['charges']);
    }

    /**
     * Les charges terminales sont exclues par défaut, incluses sur demande.
     */
    public function test_terminal_charges_are_excluded_by_default(): void
    {
        $feeTypeId = $this->createFeeType();
        $c1 = $this->createCharge($feeTypeId, 0, 'ext-terminal-1');

        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$c1}/payments", [
            'amount' => 50000,
            'method' => 'cash',
            'external_id' => 'ext-terminal-pay-1',
        ])->assertStatus(201);

        $default = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges');
        $default->assertOk();
        $this->assertSame(0, $default->json('meta.charges'), 'Une charge soldée ne doit pas être proposée.');

        $included = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges?include_terminal=1');
        $included->assertOk();
        $this->assertSame(1, $included->json('meta.charges'));
    }

    /**
     * Filtre par période d'échéance : le guichet encaisse par campagne.
     */
    public function test_due_period_filter_narrows_the_selection(): void
    {
        $feeTypeId = $this->createFeeType();
        $this->createCharge($feeTypeId, 0, 'ext-period-sep', '2026-09-15');
        $this->createCharge($feeTypeId, 1, 'ext-period-nov', '2026-11-15');

        Sanctum::actingAs($this->principalA);

        $september = $this->get(
            $this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges?due_from=2026-09-01&due_to=2026-09-30'
        );
        $september->assertOk();
        $this->assertSame(1, $september->json('meta.charges'));

        $all = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges');
        $all->assertOk();
        $this->assertSame(2, $all->json('meta.charges'));
    }

    /**
     * Filtre par moyen de paiement déjà utilisé sur la charge.
     */
    public function test_paid_with_method_filter(): void
    {
        $feeTypeId = $this->createFeeType();
        $cash = $this->createCharge($feeTypeId, 0, 'ext-method-cash');
        $transfer = $this->createCharge($feeTypeId, 1, 'ext-method-transfer');

        Sanctum::actingAs($this->principalA);
        $this->postJson($this->baseUrl()."/fee-charges/{$cash}/payments", [
            'amount' => 10000,
            'method' => 'cash',
            'external_id' => 'ext-method-pay-cash',
        ])->assertStatus(201);
        $this->postJson($this->baseUrl()."/fee-charges/{$transfer}/payments", [
            'amount' => 10000,
            'method' => 'transfer',
            'external_id' => 'ext-method-pay-transfer',
        ])->assertStatus(201);

        $byCash = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges?paid_with_method=cash');
        $byCash->assertOk();
        $this->assertSame(1, $byCash->json('meta.charges'));

        $byTransfer = $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges?paid_with_method=transfer');
        $byTransfer->assertOk();
        $this->assertSame(1, $byTransfer->json('meta.charges'));
    }

    /**
     * RBAC : la direction seule, et jamais la classe d'un autre tenant.
     */
    public function test_class_roster_requires_direction_and_same_tenant(): void
    {
        Sanctum::actingAs($this->lambdaA);
        $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges')->assertStatus(403);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($principalB);
        // 404, jamais 403 : on ne révèle pas l'existence d'une classe d'un
        // autre tenant.
        $this->get($this->baseUrl().'/classes/'.$this->classA->id.'/fee-charges')->assertStatus(404);
    }
}
