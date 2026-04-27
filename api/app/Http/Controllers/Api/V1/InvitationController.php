<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserInvitationResource;
use App\Models\Employee;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint API pour gerer les invitations cote entreprise (manager principal / RH).
 *
 * Les appels super admin sur des invitations globales transitent par
 * PlatformCompanyController.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly UserInvitationService $userInvitationService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('manageInvitations', Employee::class);

        $invitations = UserInvitation::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('last_sent_at')
            ->limit(200)
            ->get();

        return UserInvitationResource::collection($invitations);
    }

    public function resend(Request $request, string $invitationId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('manageInvitations', Employee::class);

        /** @var UserInvitation $invitation */
        $invitation = UserInvitation::query()
            ->where('id', $invitationId)
            ->where('company_id', $actor->company_id)
            ->firstOrFail();

        if ($invitation->accepted_at !== null) {
            return new JsonResponse([
                'error' => 'INVITATION_ALREADY_ACCEPTED',
                'message' => 'INVITATION_ALREADY_ACCEPTED',
                'localized_message' => __('errors.INVITATION_ALREADY_ACCEPTED'),
            ], 410);
        }

        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($invitation->employee_id);

        $this->userInvitationService->createAndSend(
            company: $employee->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $actor->email,
        );

        // Refresh to get the record as it exists after createAndSend (updateOrCreate
        // may have updated the row, changing updated_at and last_sent_at).
        $invitation->refresh();

        return new JsonResponse([
            'data' => [
                'id' => $invitation->id,
                'email' => $employee->email,
                'resent_at' => now()->toIso8601String(),
            ],
        ]);
    }

}
