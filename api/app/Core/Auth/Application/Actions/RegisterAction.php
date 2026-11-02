<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Exceptions\RegistrationNotAvailableException;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case : Inscription d'un nouvel employé (rôle "ordinary").
 *
 * Issue #2617 — inscription réservée aux invitations valides : sans
 * `invitation_token` valide (existant, non expiré, non consommé, email
 * correspondant), l'inscription est refusée (422 REGISTRATION_NOT_AVAILABLE).
 *
 * #3364 — la résolution est tenant-aware : l'invitation référence TOUJOURS un
 * employé existant (`user_invitations.employee_id NOT NULL`, créé par
 * UserInvitationService::createAndSend) ; on bascule sur le schéma du tenant
 * (TenantManager::setTenant, pattern UserInvitationService::accept) et on met
 * à jour CET employé au lieu d'en créer un doublon dans `shared_tenants`
 * (l'ancien code créait une ligne orpheline dont le user_lookup pointait vers
 * l'original → Hash::check échouait → 401 permanent).
 *
 * ## Correctifs onboarding (audit IAM — flux d'activation)
 *
 * 1. **Atomicité.** La consommation de l'invitation (`accepted_at`) était
 *    écrite HORS transaction, après la mise à jour de l'employé. Un échec de
 *    cette seconde écriture laissait un compte avec mot de passe ET une
 *    invitation encore ouverte → jeton réutilisable. Tout est désormais dans
 *    une seule transaction, avec `lockForUpdate()` sur l'invitation : deux
 *    requêtes concurrentes ne peuvent plus activer deux fois le même jeton
 *    (même garantie que `UserInvitationService::accept`).
 * 2. **Garde de statut employé.** Un employé `archived`/`departed` pouvait
 *    réactiver son compte via `/auth/register` alors que
 *    `UserInvitationService::accept` le refuse (410 INVITATION_REVOKED).
 *    Les deux chemins appliquent maintenant la même règle.
 * 3. **Rôle initial complet.** `manager_role` porté par l'invitation était
 *    ignoré : un RH invité obtenait `role=manager` mais `manager_role=null`,
 *    donc `isHr()`/`isPrincipal()` faux et des permissions erronées dès la
 *    première connexion. Le rôle managérial de l'invitation est désormais
 *    appliqué.
 *
 * @return array{employee: Employee, token: string, token_type: string}
 */
final class RegisterAction
{
    public function __construct(private readonly TenantManager $tenantManager) {}

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

        $email = strtolower(trim($data['email']));
        $tokenName = $data['device_name'] ?? 'api';

        return DB::transaction(function () use ($data, $email, $invitationToken, $tokenName): array {
            // Verrou pessimiste : la ligne d'invitation est claimée pour la
            // durée de la transaction. Un second appel concurrent attend puis
            // voit `accepted_at` renseigné → 422, jamais deux activations.
            $invitation = DB::table('public.user_invitations')
                ->where('token_hash', hash('sha256', $invitationToken))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                // Token absent, expiré, déjà consommé ou email différent : on ne
                // dit pas lequel (pas de fuite d'information sur les invitations).
                throw new RegistrationNotAvailableException;
            }

            /** @var Company|null $company */
            $company = Company::query()
                ->from('public.companies')
                ->whereKey($invitation->company_id)
                ->first();

            if (! $company) {
                throw new RegistrationNotAvailableException;
            }

            // Sécurité #2637 : une invitation d'une société suspendue/expirée ne
            // peut plus être activée (même garde que UserInvitationService::accept).
            abort_if(in_array($company->status, ['suspended', 'expired'], true), 403, 'COMPANY_SUSPENDED');

            $this->tenantManager->setTenant($company);

            try {
                /** @var Employee $employee */
                $employee = Employee::query()->findOrFail((int) $invitation->employee_id);

                // #7864 (D5) — un employé archivé/parti ne peut plus activer son
                // compte : l'invitation est de fait révoquée (410, même famille
                // que UserInvitationService::accept).
                abort_if(
                    in_array($employee->status, ['archived', 'departed'], true),
                    410,
                    'INVITATION_REVOKED',
                );

                // Mise à jour de l'employé existant — jamais de doublon (#3364) :
                // l'ancienne création dans shared_tenants rendait le login
                // impossible (lookup pointant vers l'original).
                $employee->first_name = $data['first_name'];
                $employee->last_name = $data['last_name'];
                $employee->email = $email;
                $employee->password_hash = Hash::make($data['password']);
                $employee->role = $invitation->role ?? $employee->role ?? 'ordinary';
                // Le rôle managérial de l'invitation doit être appliqué : sans
                // lui, un RH invité n'a aucune permission managériale.
                if (($invitation->manager_role ?? null) !== null) {
                    $employee->manager_role = $invitation->manager_role;
                }
                $employee->status = 'active';
                $employee->email_verified_at = now();
                $employee->invitation_accepted_at = now();
                $employee->save();
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            // Invitation consommée DANS la transaction : si cette écriture
            // échoue, la mise à jour de l'employé est annulée avec elle.
            DB::table('public.user_invitations')
                ->where('id', $invitation->id)
                ->update(['accepted_at' => now()]);

            $token = $employee->createToken($tokenName);

            return [
                'employee' => $employee,
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ];
        });
    }
}
