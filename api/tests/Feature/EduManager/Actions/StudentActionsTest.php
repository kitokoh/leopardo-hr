<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\ArchiveEduStudentAction;
use App\Modules\EduManager\Application\Actions\CreateEduStudentAction;
use App\Modules\EduManager\Application\Actions\UpdateEduStudentAction;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-024a (#8212) — Actions du cas d'usage « élève » : création (tenant de
 * session, PII basculée en colonne chiffrée), mise à jour, archivage RGPD
 * (jamais de suppression physique).
 */
class StudentActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

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
    }

    public function test_create_student_attaches_session_tenant_and_encrypts_birth_date(): void
    {
        $student = app(CreateEduStudentAction::class)->execute($this->principalA, [
            'student_number' => 'STU-1000',
            'display_name' => 'Amina Cherif',
            'birth_date' => '2014-05-12',
        ]);

        $this->assertSame($this->companyA->getKey(), $student->getAttribute('company_id'));
        $this->assertSame('STU-1000', $student->student_number);
        // La date de naissance ne part jamais en clair : colonne chiffrée uniquement.
        $attributes = $student->getAttributes();
        $this->assertArrayNotHasKey('birth_date', $attributes);
        $this->assertNotEmpty($attributes['birth_date_encrypted'] ?? null);
    }

    public function test_update_student_keeps_birth_date_encrypted(): void
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->companyA->id,
            'student_number' => 'STU-1001',
            'display_name' => 'Yanis Merbah',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $updated = app(UpdateEduStudentAction::class)->execute($student, [
            'display_name' => 'Yanis Merbah-B',
            'birth_date' => '2013-02-01',
        ]);

        $this->assertSame('Yanis Merbah-B', $updated->display_name);
        $attributes = $updated->getAttributes();
        $this->assertArrayNotHasKey('birth_date', $attributes);
        $this->assertNotEmpty($attributes['birth_date_encrypted'] ?? null);
    }

    public function test_archive_student_is_soft_rgpd_archive(): void
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->companyA->id,
            'student_number' => 'STU-1002',
            'display_name' => 'Sara Boudiaf',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        app(ArchiveEduStudentAction::class)->execute($student);

        // Archivage RGPD : la ligne existe toujours, statut `archived`.
        $this->assertSame(EduStudent::STATUS_ARCHIVED, $student->refresh()->status);
        $this->assertDatabaseHas('edu_students', [
            'id' => $student->getKey(),
            'company_id' => $this->companyA->getKey(),
        ]);
    }
}
