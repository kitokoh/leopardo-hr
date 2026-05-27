<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainingCourseResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Http\Resources\Api\V1\TrainingSessionResource;
use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Requests\Api\V1\Training\EnrollTrainingRequest;
use App\Http\Requests\Api\V1\Training\StoreCourseTrainingRequest;
use App\Http\Requests\Api\V1\Training\StoreSessionTrainingRequest;
use App\Http\Requests\Api\V1\Training\UpdateCourseTrainingRequest;
use App\Http\Requests\Api\V1\Training\UpdateEnrollmentTrainingRequest;
use App\Http\Requests\Api\V1\Training\UpdateSessionTrainingRequest;

class TrainingController extends Controller
{
    // ── Courses ─────────────────────────────────────────────────────────────

    public function indexCourses(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = TrainingCourse::query()->where('company_id', $actor->company_id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return TrainingCourseResource::collection($query->orderBy('title')->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function storeCourse(StoreCourseTrainingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

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

    public function updateCourse(UpdateCourseTrainingRequest $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $trainingCourse->update($validated);

        return (new TrainingCourseResource($trainingCourse->fresh()))->response();
    }

    // ── Sessions ────────────────────────────────────────────────────────────

    public function indexSessions(Request $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($trainingCourse->company_id !== $user->company_id) {
            abort(404);
        }

        return TrainingSessionResource::collection($trainingCourse->sessions()->with('trainer:id,first_name,last_name')->orderByDesc('start_date')->get())
            ->response();
    }

    public function storeSession(StoreSessionTrainingRequest $request, TrainingCourse $trainingCourse): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingCourse->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

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

    public function updateSession(UpdateSessionTrainingRequest $request, TrainingSession $trainingSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingSession->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $trainingSession->update($validated);

        return (new TrainingSessionResource($trainingSession->fresh()))->response();
    }

    // ── Enrollments ─────────────────────────────────────────────────────────

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

    public function updateEnrollment(UpdateEnrollmentTrainingRequest $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($trainingEnrollment->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $trainingEnrollment->update($validated);

        return (new TrainingEnrollmentResource($trainingEnrollment->fresh()))->response();
    }
}
