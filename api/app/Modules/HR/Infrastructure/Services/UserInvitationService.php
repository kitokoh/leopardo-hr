<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\UserInvitationMail;
use App\Modules\HR\Domain\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInvitationService
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function createAndSend(
        Company $company,
        Employee $employee,
        string $invitedByType,
        string $invitedByEmail,
    ): string {
        $plainToken = Str::random(64);

        // Match on (company_id, employee_id) uniquement : si l'email de
        // l'employe a change apres la premiere invitation, on veut mettre a
        // jour l'invitation existante (et donc invalider son ancien token),
        // pas en creer une nouvelle en parallele.
        // Issue #3597 : company_id/role/manager_role non mass-assignables —
        // updateOrCreate ne peut plus porter ces clés (elles seraient
        // silencieusement ignorées à la création). Logique explicite.
        $invitation = UserInvitation::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($invitation === null) {
            $invitation = new UserInvitation();
            $invitation->company_id = $company->id;
            $invitation->employee_id = $employee->id;
        }

        $invitation->email = $employee->email;
        $invitation->schema_name = $company->schema_name;
        $invitation->role = $employee->role;
        $invitation->manager_role = $employee->manager_role;
        $invitation->invited_by_type = $invitedByType;
        $invitation->invited_by_email = $invitedByEmail;
        $invitation->token_hash = hash('sha256', $plainToken);
        $invitation->expires_at = now()->addDays(7);
        $invitation->accepted_at = null;
        $invitation->last_sent_at = now();
        $invitation->metadata = [
            'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
        ];
        $invitation->save();

        // Issue #1776 : un transport mail absent ou invalide (MAIL_MAILER non
        // configuré, MAIL_URL vide → « Unsupported mail transport [] ») ne doit
        // PAS faire échouer le flux principal (création d'employé, invitation,
        // provisioning). L'invitation reste enregistrée et valide en base —
        // l'envoi pourra être retenté (resend) une fois le mailer configuré.
        // Lien d'activation : le web client (FRONTEND_URL) est privilégié — le
        // formulaire Blade du backend reste le fallback en phase dev.
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $activationUrl = $frontendUrl !== ''
            ? $frontendUrl.'/activate/'.$plainToken
            : route('invitation.activate.show', ['token' => $plainToken]);

        try {
            Mail::to($employee->email)->send(new UserInvitationMail(
                company: $company,
                employee: $employee,
                activationUrl: $activationUrl,
                invitedByEmail: $invitedByEmail,
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $plainToken;
    }

    public function accept(string $plainToken, string $password): Employee
    {
        return DB::transaction(function () use ($plainToken, $password): Employee {
            $invitation = UserInvitation::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($invitation->accepted_at !== null, 410, 'INVITATION_ALREADY_ACCEPTED');
            abort_if($invitation->expires_at?->isPast(), 410, 'INVITATION_EXPIRED');

            $company = Company::query()->findOrFail($invitation->company_id);

            // Sécurité #2637 : une invitation d'une société suspendue/expirée ne
            // peut plus être activée.
            abort_if(in_array($company->status, ['suspended', 'expired'], true), 403, 'COMPANY_SUSPENDED');

            $this->tenantManager->setTenant($company);

            try {
                /** @var Employee $employee */
                $employee = Employee::query()->findOrFail($invitation->employee_id);
                $acceptedAt = now();

                $employee->password_hash = Hash::make($password);
                $employee->email_verified_at = $acceptedAt;
                $employee->invitation_accepted_at = $acceptedAt;
                $employee->save();
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            $invitation->accepted_at = $acceptedAt;
            $invitation->save();

            return $employee;
        });
    }
}
