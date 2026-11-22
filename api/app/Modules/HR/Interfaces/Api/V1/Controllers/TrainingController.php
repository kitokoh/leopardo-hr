<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingCourseResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Http\Resources\Api\V1\TrainingSessionResource;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use App\Modules\HR\Interfaces\Api\V1\Requests\EnrollTrainingRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreTrainingCourseRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreTrainingSessionRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateTrainingCourseRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateTrainingEnrollmentRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateTrainingSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    // â”€â”€ Courses â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function indexCourses(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = TrainingCourse::query()->where('company_id', $actor->company_id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return TrainingCourseResource::collection($query->orderBy('title')->paginate(max(1, min(100, $request->integer('per_page', 15)))))
            ->response();
    }

    public function storeCourse(StoreTrainingCourseRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $course = TrainingCourse::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

        return (new TrainingCourseResource($course))
            ->response()
            ->setStatusCode(201);
    }

    public function showCourse(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($trainingCourse->company_id !== $user->company_id) {
            abort(404);
        }

        return (new TrainingCourseResource($trainingCourse->load('sessions')))->response();
    }

    public function updateCourse(UpdateTrainingCourseRequest $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $trainingCourse->update($validated);

        return (new TrainingCourseResource($trainingCourse->fresh()))->response();
    }

    // â”€â”€ Sessions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Issue #2225 — liste TOUTES les sessions de formation de la société
     * (indépendamment du cours), pour l'écran Training du dashboard admin.
     */
    public function indexAllSessions(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $sessions = TrainingSession::query()
            ->where('company_id', $user->company_id)
            ->with(['course:id,title', 'trainer:id,first_name,last_name']);

        // #5178 : le filtre ?status= (planned|in_progress|completed|cancelled)
        // était ignoré — la liste globale renvoyait toutes les sessions.
        if ($request->filled('status')) {
            $sessions->where('status', $request->input('status'));
        }

        $sessions = $sessions->orderByDesc('start_date')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return TrainingSessionResource::collection($sessions)->response();
    }

    public function indexSessions(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($trainingCourse->company_id !== $user->company_id) {
            abort(404);
        }

        // #3948 : pagination alignée sur le contrat des autres listes.
        return TrainingSessionResource::collection(
            $trainingCourse->sessions()
                ->with('trainer:id,first_name,last_name')
                ->orderByDesc('start_date')
                ->paginate((int) ($request->input('per_page', 20)))
        )
            ->response();
    }

    /**
     * GET /training/enrollments — liste globale des inscriptions scopée tenant,
     * paginée. QA wave 2026-08-14 — T003 (#2228) : l'admin SPA (TrainingView.vue)
     * appelait cet endpoint sans route derriere → onglet Inscriptions vide.
     */
    public function indexEnrollments(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = TrainingEnrollment::query()
            ->with(['employee:id,first_name,last_name', 'session:id,training_course_id,start_date,status', 'session.course:id,title'])
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('session_id')) {
            $query->where('training_session_id', $request->integer('session_id'));
        }

        return TrainingEnrollmentResource::collection(
            $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))))
        )->response();
    }

    public function storeSession(StoreTrainingSessionRequest $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $session = TrainingSession::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'training_course_id' => $trainingCourse->id,
        ]);

        return (new TrainingSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function updateSession(UpdateTrainingSessionRequest $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $trainingSession->update($validated);

        return (new TrainingSessionResource($trainingSession->fresh()))->response();
    }

    // â”€â”€ Enrollments â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function enroll(EnrollTrainingRequest $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $enrollment = TrainingEnrollment::firstOrCreate([
            'training_session_id' => $trainingSession->id,
            'employee_id' => $validated['employee_id'],
        ], [
            'company_id' => $actor->company_id,
            'status' => 'enrolled',
        ]);

        return (new TrainingEnrollmentResource($enrollment->load('employee:id,first_name,last_name')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateEnrollment(UpdateTrainingEnrollmentRequest $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingEnrollment->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $trainingEnrollment->update($validated);

        return (new TrainingEnrollmentResource($trainingEnrollment->fresh()))->response();
    }

    /**
     * #4933 — suppression d'un cours (et de ses sessions/inscriptions).
     * Réservée aux rôles principal/rh.
     */
    public function destroyCourse(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $trainingCourse->sessions()->each(function (TrainingSession $session): void {
            $session->enrollments()->delete();
            $session->delete();
        });
        $trainingCourse->delete();

        return response()->json(null, 204);
    }

    /**
     * #4933 — suppression d'une session (et de ses inscriptions).
     */
    public function destroySession(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $trainingSession->enrollments()->delete();
        $trainingSession->delete();

        return response()->json(null, 204);
    }

    /**
     * #4933 — annulation d'une inscription (manager, ou l'employé lui-même).
     */
    public function destroyEnrollment(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingEnrollment->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $trainingEnrollment->employee_id !== $actor->id) {
            abort(403);
        }

        $trainingEnrollment->delete();

        return response()->json(null, 204);
    }
}
