<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Infrastructure\Services\EduReportService;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des rapports scolaires agrégés — EDU-018 (issue #5834).
 *
 * Dashboard global direction-only : agrégats sans détail nominatif
 * (présence par classe, inscriptions par campus, moyennes par matière,
 * capacité par campus). Aucune PII élève dans les réponses.
 */
class EduReportController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduReportService $reports) {}

    public function presence(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorizeAdmin($actor);

        return response()->json([
            'data' => $this->reports->presence(
                $actor,
                $request->filled('class_id') ? (int) $request->input('class_id') : null,
                $request->input('from'),
                $request->input('to'),
            ),
        ]);
    }

    public function enrollment(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorizeAdmin($actor);

        return response()->json([
            'data' => $this->reports->enrollment(
                $actor,
                $request->filled('campus_id') ? (int) $request->input('campus_id') : null,
                $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null,
            ),
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorizeAdmin($actor);

        return response()->json([
            'data' => $this->reports->results(
                $actor,
                $request->filled('campus_id') ? (int) $request->input('campus_id') : null,
                $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null,
            ),
        ]);
    }

    public function capacity(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorizeAdmin($actor);

        return response()->json([
            'data' => $this->reports->capacity(
                $actor,
                $request->filled('campus_id') ? (int) $request->input('campus_id') : null,
            ),
        ]);
    }

    private function authorizeAdmin(Employee $actor): void
    {
        abort_unless(EduAccess::isAdmin($actor), 403);
    }
}
