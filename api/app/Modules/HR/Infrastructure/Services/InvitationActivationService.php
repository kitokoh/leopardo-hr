<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Contracts\InvitationActivationContract;
use App\Core\Auth\Domain\Exceptions\RegistrationNotAvailableException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
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
     * consommée, ne pas être expirée et correspondre à l'e-mail soumis.
     *
     * Volontairement silencieuse sur la cause exacte (même exception pour les
     * quatre cas) : `/auth/register` est public, distinguer « expirée » de
     * « inconnue » donnerait un oracle sur les invitations en circulation.
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
    }
}
