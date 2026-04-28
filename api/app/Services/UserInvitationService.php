<?php

namespace App\Services;

use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\UserInvitation;
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
        UserInvitation::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
            ],
            [
                'email' => $employee->email,
                'schema_name' => $company->schema_name,
                'role' => $employee->role,
                'manager_role' => $employee->manager_role,
                'invited_by_type' => $invitedByType,
                'invited_by_email' => $invitedByEmail,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'last_sent_at' => now(),
                'metadata' => [
                    'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                ],
            ],
        );

        Mail::to($employee->email)->send(new UserInvitationMail(
            company: $company,
            employee: $employee,
            activationUrl: route('invitation.activate.show', ['token' => $plainToken]),
            invitedByEmail: $invitedByEmail,
        ));

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
