<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Contracts\InvitationActivationContract;
use App\Core\Auth\Domain\Exceptions\RegistrationNotAvailableException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation du cas d'usage UNIQUE d'activation d'invitation employé.
 *
 * ## Pourquoi cette classe existe
 *
 * Deux surfaces publiques activaient la même invitation avec deux
 * implémentations divergentes :
 *
 *  - `POST /onboarding/invitation/{token}/activate` (OnboardingController)
 *    → `UserInvitationService::accept()` puis `AuthService::login()` ;
 *  - `POST /auth/register` (RegisterAction)
 *    → sa propre transaction, puis `$employee->createToken($name)` SANS
 *      abilities tenant.
 *
 * La seconde produisait un jeton que `TenantMiddleware` ne pouvait pas
 * résoudre (`tenant_schema:` / `tenant_company:` / `tenant_employee:`
 * absents) : l'employé activé arrivait sur un dashboard 403 alors que
 * l'activation avait réussi. Elle ignorait aussi les accès ressource
 * pré-assignés à l'invitation (#7601) et n'appliquait pas le `manager_role`
 * porté par l'invitation.
 *
 * Les deux surfaces partagent désormais CE chemin, via le contrat
 * `InvitationActivationContract` (garde #8717 : ni Core→Modules concret, ni
 * Modules→Core concret). Les contrats HTTP restent inchangés — chaque
 * contrôleur continue de sérialiser sa propre enveloppe.
 */
final class InvitationActivationService implements InvitationActivationContract
{
    public function __construct(
        private readonly UserInvitationService $invitations,
        private readonly AuthService $auth,
        private readonly TenantManager $tenantManager,
    ) {}

    /**
     * @param  array{first_name?: string|null, last_name?: string|null, email?: string|null}|null  $profile
     * @return array{employee: Employee, token: ?string, token_type: string, token_expires_at: ?string}
     */
    public function activate(
        string $plainToken,
        string $password,
        ?array $profile = null,
        string $deviceName = 'api',
        bool $registrationMode = false,
    ): array {
        if ($registrationMode) {
            $this->assertRegistrationInvitationIsUsable($plainToken, $profile['email'] ?? null);
        }

        // `accept()` porte TOUTE l'écriture (transaction, verrou pessimiste,
        // gardes société/employé, consommation de l'invitation, profil, rôle
        // managérial, accès pré-assignés). Aucune duplication ici.
        $employee = $this->invitations->accept($plainToken, $password, $profile);

        if ($registrationMode) {
            // Contrat `/auth/register` : un jeton est TOUJOURS rendu. Un échec
            // de connexion automatique à ce stade signalerait une incohérence
            // réelle (compte activé mais non connectable) — on ne la masque
            // pas derrière un `token: null`.
            $auth = $this->auth->login((string) $employee->email, $password, $deviceName);

            return [
                'employee' => $employee,
                'token' => $auth['token'],
                'token_type' => $auth['token_type'],
                'token_expires_at' => $auth['token_expires_at'],
            ];
        }

        try {
            $auth = $this->auth->login((string) $employee->email, $password, $deviceName);

            return [
                'employee' => $employee,
                'token' => $auth['token'],
                'token_type' => $auth['token_type'],
                'token_expires_at' => $auth['token_expires_at'],
            ];
        } catch (\Throwable $e) {
            // Le compte EST activé : on ne casse pas l'activation pour un échec
            // de connexion automatique, on dégrade vers la connexion manuelle.
            Log::warning('onboarding.activate.autologin_failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'employee' => $employee,
                'token' => null,
                'token_type' => 'Bearer',
                'token_expires_at' => null,
            ];
        }
    }

    /**
     * Garde du mode inscription : l'invitation doit exister, ne pas être
     * consommée, ne pas être expirée, correspondre à l'e-mail soumis, ET viser
     * un employé ACTIF.
     *
     * ## Décision produit du round 13 (#9096) — option B « refuser AVANT d'écrire »
     *
     * Avant ce correctif, `/auth/register` divergeait du mode lien sur un
     * employé dont le statut avait changé après l'émission de l'invitation :
     *
     *  - employé **suspendu** → `accept()` ÉCRIVAIT (activation, invitation
     *    consommée, mot de passe posé) puis `login()` levait 403
     *    `EMPLOYEE_NOT_ACTIVE` : le client affichait un échec sur un compte
     *    pourtant activé et un lien pourtant consommé — plus aucun réessai
     *    possible, seul « mot de passe oublié » restait ;
     *  - employé **archivé/parti** → 410 `INVITATION_REVOKED`, un code qui,
     *    sur une route PUBLIQUE, distingue l'état exact de l'invitation et
     *    permet d'énumérer les invitations en circulation.
     *
     * Le contrat public retenu est le 422 `REGISTRATION_NOT_AVAILABLE`,
     * **indifférencié** : il ne révèle pas la cause (inconnue / expirée /
     * consommée / statut de l'employé), et il est levé **avant toute
     * écriture** — l'invitation reste ouverte et le lien reste réutilisable
     * une fois l'employé réactivé.
     *
     * Le mode lien (`/onboarding/invitation/{token}/activate`) garde son
     * contrat propre (410 explicite, 201 avec dégradation `token: null`) : ces
     * deux surfaces ne portent pas le même public et n'ont pas à partager la
     * même granularité d'erreur.
     */
    private function assertRegistrationInvitationIsUsable(string $plainToken, ?string $email): void
    {
        if ($plainToken === '' || $email === null || $email === '') {
            throw new RegistrationNotAvailableException;
        }

        $invitation = DB::table('public.user_invitations')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->where('email', strtolower(trim($email)))
            ->first();

        if ($invitation === null) {
            throw new RegistrationNotAvailableException;
        }

        // Le statut de l'employé est vérifié ICI, avant l'écriture : c'est tout
        // l'objet de l'option B. `accept()` porte déjà ce contrôle en mode lien
        // (410 explicite) ; en mode inscription, on refuse plus tôt et sans
        // distinguer la cause.
        $company = Company::query()->find($invitation->company_id);

        if ($company === null) {
            throw new RegistrationNotAvailableException;
        }

        $this->tenantManager->setTenant($company);

        try {
            $employee = Employee::query()->find($invitation->employee_id);
        } finally {
            $this->tenantManager->resetToPrevious();
        }

        if ($employee === null || $employee->status !== 'active') {
            throw new RegistrationNotAvailableException;
        }
    }
}
