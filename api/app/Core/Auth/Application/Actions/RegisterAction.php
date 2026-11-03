<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Contracts\InvitationActivationContract;
use App\Core\Auth\Domain\Exceptions\RegistrationNotAvailableException;
use App\Core\Auth\Domain\Models\Employee;

/**
 * Use Case : inscription d'un employé invité (`POST /auth/register`).
 *
 * Issue #2617 — inscription réservée aux invitations valides : sans
 * `invitation_token` exploitable (existant, non expiré, non consommé, email
 * correspondant), l'inscription est refusée (422 REGISTRATION_NOT_AVAILABLE).
 *
 * ## Convergence des deux chemins d'activation
 *
 * Cette Action ne porte plus AUCUNE logique d'activation : elle délègue au
 * contrat `InvitationActivationContract`, le cas d'usage partagé avec
 * `POST /onboarding/invitation/{token}/activate`.
 *
 * Avant cette convergence, les deux surfaces divergeaient sur trois points
 * qui produisaient des comptes incohérents :
 *
 *  1. **Jeton sans abilities tenant.** `RegisterAction` forgeait le jeton avec
 *     `$employee->createToken($name)` : aucune ability `tenant_schema:` /
 *     `tenant_company:` / `tenant_employee:`, donc `TenantMiddleware` ne
 *     pouvait pas résoudre le contexte et l'employé fraîchement inscrit
 *     recevait un 403 sur son dashboard. Le chemin partagé passe par
 *     `AuthService::login()`, qui construit ces abilities, applique le quota
 *     de jetons (#7009) et positionne `last_login_at`.
 *  2. **Accès ressource pré-assignés ignorés.** `UserInvitationService::accept`
 *     matérialise les `resource_assignments` portés par l'invitation (#7601) ;
 *     la copie locale de `RegisterAction` ne le faisait pas — un employé
 *     invité avec un périmètre d'établissement arrivait sans périmètre.
 *  3. **`manager_role` ignoré.** Un RH invité obtenait `role=manager` mais
 *     `manager_role=null`, donc `isHr()` faux et des permissions erronées.
 *
 * Le contrat HTTP de `/auth/register` est inchangé : 201 + `{data, token}`,
 * et 422 `REGISTRATION_NOT_AVAILABLE` pour toute invitation inexploitable.
 *
 * @return array{employee: Employee, token: string, token_type: string}
 */
final class RegisterAction
{
    public function __construct(
        private readonly InvitationActivationContract $activateInvitation,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, password: string, invitation_token?: string|null, device_name?: string}  $data
     * @return array{employee: Employee, token: string, token_type: string}
     */
    public function execute(array $data): array
    {
        $invitationToken = $data['invitation_token'] ?? null;
        if (! is_string($invitationToken) || $invitationToken === '') {
            throw new RegistrationNotAvailableException;
        }

        $result = $this->activateInvitation->activate(
            plainToken: $invitationToken,
            password: $data['password'],
            profile: [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
            ],
            deviceName: $data['device_name'] ?? 'api',
            registrationMode: true,
        );

        // `registrationMode` garantit un jeton non nul (voir l'Action).
        return [
            'employee' => $result['employee'],
            'token' => (string) $result['token'],
            'token_type' => $result['token_type'],
        ];
    }
}
