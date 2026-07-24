<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyAnnouncementResource;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Infrastructure\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * PA2-COMM-004 — Manager RH envoie a entreprise equipe departement employe.
 *
 * PA2-COMM-011 — Moderation: managers can save an announcement as a draft
 * or schedule it for later (`store()` with `status`/`scheduled_at`),
 * publish a pending one immediately (`publish()`), or cancel a
 * draft/scheduled one before it fans out (`cancel()`). Recipients other
 * than the author only ever see `published` announcements; draft/
 * scheduled/cancelled rows stay visible to their author (and to
 * principal/RH, who can moderate anyone's announcements) so the
 * moderation workflow has something to list before publication.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    /**
     * List announcements the authenticated employee can see: every
     * *published* announcement addressed to the whole company, their own
     * department, or to them directly, plus every announcement they
     * authored themselves regardless of status (draft/scheduled/
     * cancelled/published), plus — for principal/RH moderators — every
     * draft/scheduled/cancelled announcement in the company so they can
     * moderate on behalf of other managers.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $canModerateOthers = $actor->hasManagerRole('principal', 'rh');

        $query = CompanyAnnouncement::query()
            ->where('company_id', $actor->company_id)
            ->where(function ($scope) use ($actor, $canModerateOthers): void {
                $scope->where('created_by', $actor->id)
                    ->orWhere(function ($visibleScope) use ($actor, $canModerateOthers): void {
                        // Non-authors only ever see published announcements
                        // unless they are a company-wide moderator.
                        $visibleScope->when(
                            ! $canModerateOthers,
                            fn ($q) => $q->where('status', CompanyAnnouncement::STATUS_PUBLISHED)
                        )->where(function ($audienceScope) use ($actor): void {
                            $audienceScope->where('audience_type', CompanyAnnouncement::AUDIENCE_COMPANY)
                                ->orWhere(function ($departmentScope) use ($actor): void {
                                    $departmentScope->where('audience_type', CompanyAnnouncement::AUDIENCE_DEPARTMENT)
                                        ->where('audience_department_id', $actor->department_id ?? -1);
                                })
                                ->orWhere(function ($employeeScope) use ($actor): void {
                                    $employeeScope->where('audience_type', CompanyAnnouncement::AUDIENCE_EMPLOYEE)
                                        ->where('audience_employee_id', $actor->id);
                                });
                        });
                    });
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        return CompanyAnnouncementResource::collection(
            $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'audience_type' => ['required', Rule::in(CompanyAnnouncement::audienceTypes())],
            'audience_department_id' => ['required_if:audience_type,department', 'nullable', 'integer', 'min:1'],
            'audience_employee_id' => ['required_if:audience_type,employee', 'nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in([CompanyAnnouncement::STATUS_DRAFT, CompanyAnnouncement::STATUS_SCHEDULED, CompanyAnnouncement::STATUS_PUBLISHED])],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:status,scheduled'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->authorizeAudience($actor, $data);

        $announcement = $this->announcements->publish($actor, $data);

        return (new CompanyAnnouncementResource($announcement->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Publishes a `draft`/`scheduled` announcement right now, fanning it
     * out to its audience ahead of its `scheduled_at` (if any).
     */
    public function publish(Request $request, CompanyAnnouncement $announcement): JsonResponse
    {
        $actor = $this->authorizeModeration($request, $announcement);

        try {
            $announcement = $this->announcements->publishNow($announcement);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return (new CompanyAnnouncementResource($announcement->load('author')))->response();
    }

    /**
     * Cancels a `draft`/`scheduled` announcement before it ever fans out.
     */
    public function cancel(Request $request, CompanyAnnouncement $announcement): JsonResponse
    {
        $actor = $this->authorizeModeration($request, $announcement);

        try {
            $announcement = $this->announcements->cancel($announcement, $actor);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return (new CompanyAnnouncementResource($announcement->load(['author', 'cancelledBy'])))->response();
    }

    public function destroy(Request $request, CompanyAnnouncement $announcement): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($announcement->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($announcement->created_by !== $actor->id && ! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    /**
     * Only the author or a company-wide moderator (principal/RH) may
     * publish-now/cancel a draft/scheduled announcement.
     */
    private function authorizeModeration(Request $request, CompanyAnnouncement $announcement): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($announcement->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($announcement->created_by !== $actor->id && ! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        return $actor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizeAudience(Employee $actor, array $data): void
    {
        $companyWide = $actor->hasManagerRole('principal', 'rh');

        if ($data['audience_type'] === CompanyAnnouncement::AUDIENCE_COMPANY && ! $companyWide) {
            throw ValidationException::withMessages([
                'audience_type' => 'Only principal/RH managers can broadcast to the whole company.',
            ]);
        }

        if ($data['audience_type'] === CompanyAnnouncement::AUDIENCE_DEPARTMENT) {
            $departmentId = (int) $data['audience_department_id'];
            if (! $companyWide && ! ($actor->isDept() && (int) ($actor->department_id ?? -1) === $departmentId)) {
                throw ValidationException::withMessages([
                    'audience_department_id' => 'You can only broadcast to your own department.',
                ]);
            }
        }

        if ($data['audience_type'] === CompanyAnnouncement::AUDIENCE_EMPLOYEE) {
            $employeeId = (int) $data['audience_employee_id'];
            $target = Employee::query()
                ->where('company_id', $actor->company_id)
                ->where('id', $employeeId)
                ->first();

            if ($target === null) {
                throw ValidationException::withMessages([
                    'audience_employee_id' => 'Employee not found in your company.',
                ]);
            }

            if (! $companyWide && (! $actor->isTeamScoped() || ! $actor->managesTeamMemberOf($target))) {
                throw ValidationException::withMessages([
                    'audience_employee_id' => 'You can only message employees in your own team.',
                ]);
            }
        }
    }
}
