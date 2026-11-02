<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Http\JsonResponse;
use App\Modules\Onboarding\Interfaces\Api\V1\Requests\ActivateInvitationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
        private readonly AuthService $authService,
    ) {}

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

        // Correctif onboarding (audit IAM) — `show` annonçait une invitation
        // valide pour une société suspendue/expirée : l'utilisateur remplissait
        // le formulaire puis recevait un 403 à l'activation. On applique ici la
        // même garde que `accept()` pour que l'écran dise la vérité.
        $company = \App\Core\Tenant\Domain\Models\Company::query()
            ->from('public.companies')
            ->whereKey($invitation->company_id)
            ->first();

        if ($company !== null && in_array($company->status, ['suspended', 'expired'], true)) {
            return $this->errorResponse('COMPANY_SUSPENDED', 403);
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

        // Correctif onboarding (audit IAM) — le jeton était forgé ici avec
        // `$employee->createToken($deviceName)` : il ne portait AUCUNE ability
        // `tenant_schema:` / `tenant_company:` / `tenant_employee:`. Le
        // middleware TenantMiddleware ne pouvait donc pas résoudre le contexte
        // tenant et l'employé fraîchement activé arrivait sur un dashboard
        // inaccessible (403 COMPANY_NOT_FOUND) alors que l'activation avait
        // réussi. On réutilise le chemin de connexion ÉPROUVÉ
        // (`AuthService::login`), qui construit ces abilities, applique le
        // quota de jetons (#7009) et positionne `last_login_at`.
        $deviceName = (string) $request->input('device_name', 'mobile');

        try {
            $auth = $this->authService->login($employee->email, $validated['password'], $deviceName);
            $plainToken = $auth['token'];
            $expiresAt = $auth['token_expires_at'];
        } catch (\Throwable $e) {
            // Le compte EST activé : on ne casse pas l'activation pour un échec
            // de connexion automatique, on dégrade vers la connexion manuelle.
            Log::warning('onboarding.activate.autologin_failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            $plainToken = null;
            $expiresAt = null;
        }

        return new JsonResponse([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'email' => $employee->email,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'role' => $employee->role,
                    'manager_role' => $employee->manager_role,
                ],
                'token' => $plainToken,
                'token_type' => 'Bearer',
                'token_expires_at' => $expiresAt,
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
