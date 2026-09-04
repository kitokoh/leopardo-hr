<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord de l'administration scolaire — EDU-011 (issue #5827).
 *
 * Navigation rôle-aware : direction uniquement (un enseignant utilise
 * /teacher/workspace). Renvoie les compteurs et sections de l'interface
 * d'administration (campus, années, classes, élèves, inscriptions,
 * évaluations, bulletins, frais) — l'UI consomme ce contrat pour construire
 * sa navigation et ses états vides.
 */
class EduDashboardController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(EduAccess::isAdmin($actor), 403, 'EDU_ADMIN_ONLY');

        $companyId = $actor->company_id;

        $campuses = EduCampus::query()->where('company_id', $companyId)->count();
        $academicYears = EduAcademicYear::query()->where('company_id', $companyId)->count();
        $classes = EduClass::query()->where('company_id', $companyId)->count();
        $students = EduStudent::query()->where('company_id', $companyId)->where('status', EduStudent::STATUS_ACTIVE)->count();
        $assessments = EduAssessment::query()->where('company_id', $companyId)->count();
        $publishedReportCards = EduReportCard::query()
            ->where('company_id', $companyId)
            ->where('status', EduReportCard::STATUS_PUBLISHED)
            ->count();

        $pendingFees = EduFeeCharge::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [EduFeeCharge::STATUS_PENDING, EduFeeCharge::STATUS_PARTIAL])
            ->get();

        $admissionsByStatus = EduAdmission::query()
            ->where('company_id', $companyId)
            ->select('status')
            ->get()
            ->countBy('status')
            ->all();

        return response()->json([
            'data' => [
                'role' => 'admin',
                'navigation' => [
                    ['key' => 'campuses', 'label' => 'Campus', 'count' => $campuses, 'route' => '/edu-manager/campuses'],
                    ['key' => 'academic_years', 'label' => 'Années scolaires', 'count' => $academicYears, 'route' => '/edu-manager/academic-years'],
                    ['key' => 'classes', 'label' => 'Classes', 'count' => $classes, 'route' => '/edu-manager/classes'],
                    ['key' => 'students', 'label' => 'Élèves', 'count' => $students, 'route' => '/edu-manager/students'],
                    ['key' => 'admissions', 'label' => 'Admissions', 'count' => (int) array_sum($admissionsByStatus), 'route' => '/edu-manager/admissions'],
                    ['key' => 'assessments', 'label' => 'Évaluations', 'count' => $assessments, 'route' => '/edu-manager/assessments'],
                    ['key' => 'report_cards', 'label' => 'Bulletins publiés', 'count' => $publishedReportCards, 'route' => '/edu-manager/report-cards'],
                    ['key' => 'fees', 'label' => 'Frais scolaires', 'count' => $pendingFees->count(), 'route' => '/edu-manager/fee-charges'],
                ],
                'summary' => [
                    'admissions_by_status' => $admissionsByStatus,
                    'pending_fees_total' => round((float) $pendingFees->sum('amount'), 2),
                    'pending_fees_count' => $pendingFees->count(),
                ],
            ],
        ]);
    }
}
