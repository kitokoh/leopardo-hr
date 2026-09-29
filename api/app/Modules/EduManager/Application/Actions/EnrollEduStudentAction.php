<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Cas d'usage : inscription d'un élève à une classe (EDU-011, issue #5827).
 *
 * Consommé par `POST .../edu-manager/classes/{class}/enrollments`
 * (EduClassEnrollmentController::store). Inscription idempotente
 * (UNIQUE company_id, class_id, student_id) : un double envoi retourne
 * l'inscription existante au lieu d'échouer. La garde direction
 * (`EDU_ADMIN_ONLY`) reste au controller.
 */
class EnrollEduStudentAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Employee $actor, EduClass $class, array $data): EduClassEnrollment
    {
        $payload = [
            'company_id' => $actor->company_id,
            'class_id' => $class->getKey(),
            'student_id' => $data['student_id'],
            'academic_year_id' => $data['academic_year_id'],
            'enrolled_at' => $data['enrolled_at'] ?? now(),
            'status' => EduClassEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $actor->id,
        ];

        try {
            // SAVEPOINT (cf. 25P02) : la violation d'unicité doit être
            // contenue pour que la transaction appelante reste utilisable et
            // que le rejeu idempotent ci-dessous aboutisse.
            /** @var EduClassEnrollment $enrollment */
            $enrollment = $this->db->connection()->transaction(fn (): EduClassEnrollment => EduClassEnrollment::query()->create($payload));
        } catch (UniqueConstraintViolationException) {
            /** @var EduClassEnrollment $enrollment */
            $enrollment = EduClassEnrollment::query()
                ->where('company_id', $actor->company_id)
                ->where('class_id', $class->getAttribute('id'))
                ->where('student_id', $data['student_id'])
                ->firstOrFail();
        }

        return $enrollment;
    }
}
