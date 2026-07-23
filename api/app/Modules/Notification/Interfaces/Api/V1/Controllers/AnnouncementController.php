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

/**
 * PA2-COMM-004 — Manager RH envoie a entreprise equipe departement employe.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    /**
     * List announcements the authenticated employee can see: everything
     * addressed to the whole company, their own department, or to them
     * directly, plus everything they authored themselves.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = CompanyAnnouncement::query()
            ->where('company_id', $actor->company_id)
            ->where(function ($scope) use ($actor): void {
                $scope->where('audience_type', CompanyAnnouncement::AUDIENCE_COMPANY)
                    ->orWhere('created_by', $actor->id)
                    ->orWhere(function ($departmentScope) use ($actor): void {
                        $departmentScope->where('audience_type', CompanyAnnouncement::AUDIENCE_DEPARTMENT)
                            ->where('audience_department_id', $actor->department_id ?? -1);
                    })
                    ->orWhere(function ($employeeScope) use ($actor): void {
                        $employeeScope->where('audience_type', CompanyAnnouncement::AUDIENCE_EMPLOYEE)
                            ->where('audience_employee_id', $actor->id);
                    });
            });

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        return CompanyAnnouncementResource::collection(
            $query->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage)
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
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->authorizeAudience($actor, $data);

        $announcement = $this->announcements->publish($actor, $data);

        return (new CompanyAnnouncementResource($announcement->load('author')))
            ->response()
            ->setStatusCode(201);
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
