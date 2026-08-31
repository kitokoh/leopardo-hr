<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Policies\EduCampusPolicy;
use App\Modules\EduManager\Domain\Policies\EduGuardianPolicy;
use App\Modules\EduManager\Domain\Policies\EduStudentGuardianPolicy;
use App\Modules\EduManager\Domain\Policies\EduStudentPolicy;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5818 (EDU-002) — relations autorisées : un responsable légal ne
 * voit QUE les élèves qui lui sont explicitement liés, et jamais ceux d'un
 * autre gardien ni d'un autre tenant (« tests guardian non autorisé »).
 *
 * Couvre aussi : accès gestionnaire (principal/rh/manager) borné au tenant,
 * `viewGrades` conditionné à `can_view_grades`, policy gardien sur son
 * propre profil uniquement, et chiffrement de la PII au repos.
 */
class EduGuardianAccessTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    public function test_manager_can_view_any_student_of_own_tenant(): void
    {
        $student = $this->student($this->company, 'S-001');
        $otherTenantStudent = $this->student($this->otherCompany, 'S-X1');

        $this->assertTrue(app(EduStudentPolicy::class)->view($this->manager, $student));
        // Un gestionnaire ne voit JAMAIS les élèves d'un autre tenant.
        $this->assertFalse(app(EduStudentPolicy::class)->view($this->manager, $otherTenantStudent));
    }

    public function test_plain_employee_is_not_authorized(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $student = $this->student($this->company, 'S-001');

        $this->assertFalse(app(EduStudentPolicy::class)->view($employee, $student));
        $this->assertFalse(app(EduStudentPolicy::class)->viewAny($employee));
    }

    public function test_guardian_can_view_only_linked_student(): void
    {
        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $linked = $this->student($this->company, 'S-001');
        $otherStudent = $this->student($this->company, 'S-002');
        $this->linkGuardian($guardianActor, $linked);

        $policy = app(EduStudentPolicy::class);

        // Élève lié : autorisé.
        $this->assertTrue($policy->view($guardianActor, $linked));
        // Élève du MÊME tenant mais non lié : REFUSÉ (guardian non autorisé).
        $this->assertFalse($policy->view($guardianActor, $otherStudent));
    }

    public function test_guardian_never_sees_other_tenant_student(): void
    {
        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $linked = $this->student($this->company, 'S-001');
        $this->linkGuardian($guardianActor, $linked);

        $otherTenantStudent = $this->student($this->otherCompany, 'S-X1');

        $this->assertFalse(app(EduStudentPolicy::class)->view($guardianActor, $otherTenantStudent));
    }

    public function test_guardian_without_grade_right_cannot_view_grades(): void
    {
        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $student = $this->student($this->company, 'S-001');
        $this->linkGuardian($guardianActor, $student, canViewGrades: false);

        $this->assertTrue(app(EduStudentPolicy::class)->view($guardianActor, $student));
        $this->assertFalse(app(EduStudentPolicy::class)->viewGrades($guardianActor, $student));
    }

    public function test_guardian_with_grade_right_can_view_grades(): void
    {
        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $student = $this->student($this->company, 'S-001');
        $this->linkGuardian($guardianActor, $student, canViewGrades: true);

        $this->assertTrue(app(EduStudentPolicy::class)->viewGrades($guardianActor, $student));
    }

    public function test_guardian_policy_is_self_only(): void
    {
        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        /** @var Employee $otherGuardianActor */
        $otherGuardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $own = $this->guardian($this->company, $guardianActor);
        $other = $this->guardian($this->company, $otherGuardianActor);

        $policy = app(EduGuardianPolicy::class);

        // Un gardien voit son propre profil…
        $this->assertTrue($policy->view($guardianActor, $own));
        // … jamais celui d'un autre responsable (PII).
        $this->assertFalse($policy->view($guardianActor, $other));
        // Le gestionnaire du tenant voit tous les profils du tenant.
        $this->assertTrue($policy->view($this->manager, $other));
        $this->assertFalse($policy->view($this->manager, $this->guardian($this->otherCompany, $otherGuardianActor)));
    }

    public function test_campus_and_link_policies_are_manager_scoped(): void
    {
        $campusA = $this->campus($this->company, 'MAIN');
        $campusB = $this->campus($this->otherCompany, 'MAIN');

        $this->assertTrue(app(EduCampusPolicy::class)->view($this->manager, $campusA));
        $this->assertFalse(app(EduCampusPolicy::class)->view($this->manager, $campusB));

        $student = $this->student($this->company, 'S-001');
        $guardian = $this->guardian($this->company, $this->manager);
        /** @var EduStudentGuardian $link */
        $link = EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
        ]);

        $this->assertTrue(app(EduStudentGuardianPolicy::class)->view($this->manager, $link));
    }

    public function test_student_birth_date_is_encrypted_at_rest(): void
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->company->id,
            'student_number' => 'S-001',
            'display_name' => 'Élève PII',
            'birth_date_encrypted' => '1999-01-01',
        ]);

        $this->assertSame('1999-01-01', $student->birth_date_encrypted);

        $raw = \Illuminate\Support\Facades\DB::table('edu_students')
            ->where('id', $student->id)
            ->value('birth_date_encrypted');

        // Au repos : valeur chiffrée (enveloppe Laravel base64), jamais la date en clair.
        $this->assertIsString($raw);
        $this->assertNotSame('1999-01-01', $raw);
        $this->assertStringStartsWith('eyJ', (string) $raw); // base64('{"iv":...') — cast `encrypted`
        $this->assertSame('1999-01-01', \Illuminate\Support\Facades\Crypt::decryptString((string) $raw));
    }

    private function student(Company $company, string $number): EduStudent
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $company->id,
            'student_number' => $number,
            'display_name' => $this->faker->name(),
        ]);

        return $student;
    }

    private function campus(Company $company, string $code): \App\Modules\EduManager\Domain\Models\EduCampus
    {
        /** @var \App\Modules\EduManager\Domain\Models\EduCampus $campus */
        $campus = \App\Modules\EduManager\Domain\Models\EduCampus::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Campus '.$code,
        ]);

        return $campus;
    }

    private function guardian(Company $company, Employee $employee): EduGuardian
    {
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ]);

        return $guardian;
    }

    private function linkGuardian(Employee $guardianActor, EduStudent $student, bool $canViewGrades = false): EduStudentGuardian
    {
        $guardian = $this->guardian($this->company, $guardianActor);

        /** @var EduStudentGuardian $link */
        $link = EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'can_view_grades' => $canViewGrades,
        ]);

        return $link;
    }
}
