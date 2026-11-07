<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Contracts;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Contrat d'activation d'une invitation employé — cas d'usage UNIQUE.
 *
 * ## Pourquoi un contrat
 *
 * `Core/Auth` ne doit pas dépendre d'un service concret de `Modules/*`, et un
 * module ne doit pas dépendre d'une Action concrète de `Core`
 * (garde `IdentityLayerBoundariesArchitectureTest`, #8717 écart I-3). Les deux
 * surfaces publiques d'activation dépendent donc de CETTE interface :
 *
 *  - `POST /auth/register` (Core/Auth) ;
 *  - `POST /onboarding/invitation/{token}/activate` (Modules/Onboarding).
 *
 * L'implémentation qui connaît `UserInvitationService` (module HR) est liée
 * dans `AppServiceProvider`.
 *
 * ## Ce que le contrat garantit
 *
 * Une seule règle d'activation pour toutes les surfaces :
 *  - l'invitation est vérifiée, verrouillée et consommée atomiquement ;
 *  - les gardes société suspendue/expirée et employé archivé/parti s'appliquent ;
 *  - le profil et le rôle portés par l'invitation sont appliqués ;
 *  - les accès ressource pré-assignés prennent effet (#7601) ;
 *  - le statut de l'employé n'est jamais forcé (une suspension tient) ;
 *  - le jeton rendu porte les abilities tenant (`tenant_schema:`,
 *    `tenant_company:`, `tenant_employee:`), sans quoi `TenantMiddleware` ne
 *    peut pas résoudre le contexte et le dashboard répond 403.
 */
interface InvitationActivationContract
{
    /**
     * Active l'invitation et émet un jeton Sanctum utilisable.
     *
     * @param  array{first_name?: string|null, last_name?: string|null, email?: string|null}|null  $profile
     *                                                                                                       Profil saisi à l'activation. `null` = activation par lien,
     *                                                                                                       le profil existant est conservé.
     * @param  bool  $registrationMode  `true` pour `/auth/register` : toute invitation inexploitable
     *                                  donne `422 REGISTRATION_NOT_AVAILABLE` (contrat historique, pas
     *                                  d'oracle sur l'état des invitations) et un jeton est TOUJOURS rendu.
     *                                  `false` pour l'activation par lien : les codes 404/410/403 du
     *                                  service remontent, et un échec de connexion automatique dégrade
     *                                  en `token: null` sans casser l'activation.
     * @return array{employee: Employee, token: ?string, token_type: string, token_expires_at: ?string}
     *
     * @throws \Throwable Selon l'état de l'invitation, de la société ou de l'employé.
     */
    public function activate(
        string $plainToken,
        string $password,
        ?array $profile = null,
        string $deviceName = 'api',
        bool $registrationMode = false,
    ): array;
}
