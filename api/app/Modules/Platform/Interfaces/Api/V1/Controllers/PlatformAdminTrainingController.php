<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingCourseResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Http\Resources\Api\V1\TrainingSessionResource;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Training — vue cross-tenant super-admin (contrat SPA front/admin-dashboard,
 * issue #2634 : TrainingView appelait /v1/training/sessions et
 * /v1/training/enrollments qui sont scopées tenant api.manager → 401).
 */
class PlatformAdminTrainingController extends Controller
{
    /**
     * GET /admin/training/courses — tous les cours de formation, tous tenants,
     * paginés (avec société et nombre de sessions). Équivalent cross-tenant de
     * GET /v1/training/courses (scopé tenant api.manager → 401 super-admin).
     */
    public function indexCourses(Request $request): JsonResponse
    {
        $courses = TrainingCourse::query()
            ->withCount('sessions')
            ->leftJoin('companies', 'companies.id', '=', 'training_courses.company_id')
            ->select('training_courses.*', 'companies.name as company_name')
            ->orderBy('training_courses.title')
            ->paginate($request->integer('per_page', 20));

        return TrainingCourseResource::collection($courses)->response();
    }

    /**
     * GET /admin/training/sessions — toutes les sessions de formation,

     * tous tenants, paginées (avec société + formateur + cours).
     */
    public function indexSessions(Request $request): JsonResponse
    {
        $sessions = TrainingSession::query()
            ->with(['course:id,title', 'trainer:id,first_name,last_name'])
            ->leftJoin('companies', 'companies.id', '=', 'training_sessions.company_id')
            ->select('training_sessions.*', 'companies.name as company_name')
            ->orderByDesc('training_sessions.start_date')
            ->paginate($request->integer('per_page', 20));

        return TrainingSessionResource::collection($sessions)->response();
    }

    /**
     * GET /admin/training/enrollments — toutes les inscriptions formation,
     * tous tenants, paginées (avec employé + session + société).
     */
    public function indexEnrollments(Request $request): JsonResponse
    {
        $query = TrainingEnrollment::query()
            ->with(['employee:id,first_name,last_name', 'session:id,training_course_id,start_date,status'])
            ->leftJoin('companies', 'companies.id', '=', 'training_enrollments.company_id')
            ->select('training_enrollments.*', 'companies.name as company_name');

        if ($request->filled('status')) {
            $query->where('training_enrollments.status', $request->input('status'));
        }

        return TrainingEnrollmentResource::collection(
            $query->orderByDesc('training_enrollments.created_at')->paginate($request->integer('per_page', 15))
        )->response();
    }
}
