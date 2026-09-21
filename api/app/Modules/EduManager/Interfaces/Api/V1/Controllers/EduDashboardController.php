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
 *
 * #7985 — les agrégats (frais en attente, admissions par statut) sont
 * calculés EN SQL (`count(*)` / `sum(amount)` / `group by`) : l'ancienne
 * version chargeait toutes les lignes (`->get()`) puis sommait/comptait en
 * PHP, soit O(n) lignes transférées et hydratées à chaque ouverture du
 * dashboard. Le payload RESTE identique (mêmes clés, mêmes types).
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

        // #7985 — agrégat SQL : count + sum en UNE requête, aucune ligne
        // hydratée (l'ancien `->get()` chargeait toutes les charges en
        // attente puis `count()`/`sum('amount')` en PHP).
        $pendingFeesAggregate = EduFeeCharge::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [EduFeeCharge::STATUS_PENDING, EduFeeCharge::STATUS_PARTIAL])
            ->selectRaw('count(*) as pending_count, coalesce(sum(amount), 0) as pending_amount')
            ->first();

        $pendingFeesCount = (int) ($pendingFeesAggregate?->getAttribute('pending_count') ?? 0);
        $pendingFeesTotal = round((float) ($pendingFeesAggregate?->getAttribute('pending_amount') ?? 0), 2);

        // #7985 — `countBy('status')` en PHP → `group by status` SQL : un
        // agrégat par statut, jamais les dossiers eux-mêmes.
        $admissionsByStatus = EduAdmission::query()
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
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
                    ['key' => 'fees', 'label' => 'Frais scolaires', 'count' => $pendingFeesCount, 'route' => '/edu-manager/fee-charges'],
                ],
                'summary' => [
                    'admissions_by_status' => $admissionsByStatus,
                    'pending_fees_total' => $pendingFeesTotal,
                    'pending_fees_count' => $pendingFeesCount,
                ],
            ],
        ]);
    }
}
