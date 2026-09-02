<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduRetentionService;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API RGPD EduManager — EDU-019 (issue #5835).
 *
 * Anonymisation et export individuel (droit d'accès), direction uniquement,
 * tenant-scopés, audit non altérable (AuditLog `edu.privacy.*`).
 */
class EduRetentionController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduRetentionService $retention)
    {
    }

    public function anonymize(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403);
        $this->assertSameTenant($student, $actor->company_id);

        $anonymized = $this->retention->anonymizeStudent($actor, $student);

        return response()->json([
            'data' => [
                'id' => (int) $anonymized->getAttribute('id'),
                'student_number' => $anonymized->student_number,
                'status' => $anonymized->status,
                'anonymized' => true,
            ],
        ]);
    }

    public function privacyExport(Request $request, EduStudent $student): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403);
        $this->assertSameTenant($student, $actor->company_id);

        return response()->json([
            'data' => $this->retention->exportIndividual($actor, $student),
        ]);
    }
}
