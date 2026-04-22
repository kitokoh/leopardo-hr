<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            ->get()
            ->map(fn (UserInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'manager_role' => $invitation->manager_role,
                'employee_id' => $invitation->employee_id,
                'expires_at' => optional($invitation->expires_at)->toIso8601String(),
                'accepted_at' => optional($invitation->accepted_at)->toIso8601String(),
                'last_sent_at' => optional($invitation->last_sent_at)->toIso8601String(),
                'status' => $this->statusFor($invitation),
            ]);

        return new JsonResponse(['data' => $invitations->values()]);
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
                'message' => 'Invitation deja acceptee.',
                'error' => 'INVITATION_ALREADY_ACCEPTED',
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

        return new JsonResponse([
            'data' => [
                'id' => $invitation->fresh()->id,
                'email' => $employee->email,
                'resent_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function statusFor(UserInvitation $invitation): string
    {
        if ($invitation->accepted_at !== null) {
            return 'accepted';
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return 'expired';
        }

        return 'pending';
    }
}
