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

        $invitation = DB::table('public.user_invitations')
            ->where('token_hash', hash('sha256', $invitationToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->where('email', $email)
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

            // Mise à jour de l'employé existant — jamais de doublon (#3364) :
            // l'ancienne création dans shared_tenants rendait le login
            // impossible (lookup pointant vers l'original).
            $employee->first_name = $data['first_name'];
            $employee->last_name = $data['last_name'];
            $employee->email = $email;
            $employee->password_hash = Hash::make($data['password']);
            $employee->role = $invitation->role ?? $employee->role ?? 'ordinary';
            $employee->status = 'active';
            $employee->email_verified_at = now();
            $employee->invitation_accepted_at = now();
            $employee->save();
        } finally {
            $this->tenantManager->resetToPrevious();
        }

        // Invitation consommée (idempotence : seule la première passe).
        DB::table('public.user_invitations')
            ->where('id', $invitation->id)
            ->update(['accepted_at' => now()]);

        $tokenName = $data['device_name'] ?? 'api';
        $token = $employee->createToken($tokenName);

        return [
            'employee' => $employee,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
