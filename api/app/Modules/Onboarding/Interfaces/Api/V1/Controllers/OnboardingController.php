<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Http\JsonResponse;
use App\Modules\Onboarding\Interfaces\Api\V1\Requests\ActivateInvitationRequest;
use Illuminate\Http\Request;

/**
 * Module 6 — Public Onboarding API
 *
 * Endpoints publics (sans auth) permettant à un employé invité de :
 *   1. Vérifier la validité de son token d'invitation
 *   2. Activer son compte en définissant son mot de passe
 *
 * Utilisé par l'app mobile et le web frontend.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly UserInvitationService $userInvitationService) {}

    /**
     * GET /onboarding/invitation/{token}
     *
     * Vérifie qu'un token d'invitation est valide et retourne les infos
     * nécessaires pour afficher le formulaire d'activation (nom, email, company).
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            return $this->errorResponse('INVITATION_NOT_FOUND', 404);
        }

        if ($invitation->accepted_at !== null) {
            return $this->errorResponse('INVITATION_ALREADY_ACCEPTED', 410);
        }

        if ($invitation->expires_at?->isPast()) {
            return $this->errorResponse('INVITATION_EXPIRED', 410);
        }

        return new JsonResponse([
            'data' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'manager_role' => $invitation->manager_role,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'employee_name' => $invitation->metadata['employee_name'] ?? null,
            ],
        ]);
    }

    /**
     * POST /onboarding/invitation/{token}/activate
     *
     * Active le compte de l'employé en définissant son mot de passe.
     * Retourne un token Sanctum pour connexion immédiate après activation.
     */
    public function activate(ActivateInvitationRequest $request, string $token): JsonResponse
    {
        $validated = $request->validated();

        // Check token validity before calling service
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            return $this->errorResponse('INVITATION_NOT_FOUND', 404);
        }

        if ($invitation->accepted_at !== null) {
            return $this->errorResponse('INVITATION_ALREADY_ACCEPTED', 410);
        }

        if ($invitation->expires_at?->isPast()) {
            return $this->errorResponse('INVITATION_EXPIRED', 410);
        }

        $employee = $this->userInvitationService->accept($token, $validated['password']);

        // Issue a Sanctum token for immediate login after activation
        $deviceName = $request->input('device_name', 'mobile');
        $sanctumToken = $employee->createToken($deviceName);

        return new JsonResponse([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'email' => $employee->email,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'role' => $employee->role,
                ],
                'token' => $sanctumToken->plainTextToken,
                'token_type' => 'Bearer',
                'token_expires_at' => null,
            ],
            'message' => 'ACCOUNT_ACTIVATED',
        ], 201);
    }

    /**
     * QA 2026-08-15 (#2653) : shape d'erreur conforme au contrat API
     * ({error, message, localized_message}) au lieu de l'objet imbriqué
     * historique {error: {code, message}}.
     */
    private function errorResponse(string $code, int $status): JsonResponse
    {
        $translated = __("errors.{$code}");

        return new JsonResponse([
            'error' => $code,
            'message' => $code,
            'localized_message' => $translated !== "errors.{$code}" ? $translated : $code,
        ], $status);
    }
}
