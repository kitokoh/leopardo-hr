<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Exceptions\RegistrationNotAvailableException;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Use Case : Inscription d'un nouvel employé (rôle "ordinary").
 *
 * Issue #2617 — inscription réservée aux invitations valides : sans
 * `invitation_token` valide (existant, non expiré, non consommé, email
 * correspondant), l'inscription est refusée (422 REGISTRATION_NOT_AVAILABLE).
 * L'employé est rattaché au `company_id` de l'invitation (plus d'employé
 * sans tenant), et l'invitation est marquée consommée.
 *
 * @return array{employee: Employee, token: string, token_type: string}
 */
final class RegisterAction
{
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

        /** @var Employee $employee */
        $employee = Employee::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'password_hash' => Hash::make($data['password']),
            'role' => $invitation->role ?? 'ordinary',
            'status' => 'active',
            'company_id' => $invitation->company_id,
        ]);

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
