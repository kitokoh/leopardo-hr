<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserInvitationResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
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

        return UserInvitationResource::collection($invitations)->response();
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

    /**
     * Issue #7762 (spec MISSION_ESPACE_CLIENT §3.1) — révocation d'une
     * invitation en attente : la ligne est SUPPRIMÉE (le schéma
     * `user_invitations` n'a pas de soft delete), donc son `token_hash`
     * disparaît et `UserInvitationService::accept()` (firstOrFail sur le hash)
     * répond 404 — le lien reçu par email devient inutilisable immédiatement.
     * Une invitation déjà acceptée n'est pas révocable (410, comme resend) :
     * le compte existe, c'est l'archivage de l'employé qui retire l'accès.
     */
    public function destroy(Request $request, string $invitationId): JsonResponse
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

        $invitation->delete();

        return new JsonResponse([
            'data' => [
                'id' => $invitationId,
                'revoked_at' => now()->toIso8601String(),
            ],
        ]);
    }
}

