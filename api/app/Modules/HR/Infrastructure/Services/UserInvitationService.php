<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry;
use App\Core\Tenant\TenantManager;
use App\Mail\UserInvitationMail;
use App\Modules\HR\Domain\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInvitationService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ResourceTypeRegistry $resourceTypes,
    ) {}

    /**
     * @param  list<array{resource_type: string, resource_id: int, access_level: string}>  $resourceAssignments
     *                                                                                                           #7601 (R4 de l'épique #7597) — invitation pré-assignée : les accès
     *                                                                                                           ressource voyagent dans l'invitation et sont créés à l'ACTIVATION
     *                                                                                                           (l'employé n'a de session qu'à ce moment-là ; s'il n'active jamais,
     *                                                                                                           aucun accès n'existe).
     */
    public function createAndSend(
        Company $company,
        Employee $employee,
        string $invitedByType,
        string $invitedByEmail,
        array $resourceAssignments = [],
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
            $invitation = new UserInvitation;
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
        $metadata = [
            'employee_name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
        ];

        // Une ressource inexistante (ou d'un autre tenant) n'est jamais
        // pré-assignable : refus explicite, comme le PUT R1.
        if ($resourceAssignments !== []) {
            foreach ($resourceAssignments as $entry) {
                abort_unless(
                    $this->resourceTypes->exists($entry['resource_type'], $entry['resource_id'], $company->id),
                    422,
                    __('errors.RESOURCE_NOT_FOUND'),
                );
            }
            $metadata['resource_assignments'] = $resourceAssignments;
        }

        $invitation->metadata = $metadata;
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

                // #7601 — les accès pré-assignés à l'invitation prennent effet
                // à l'activation. Une ressource disparue entre-temps est
                // ignorée (elle n'existe plus, il n'y a rien à donner).
                $this->applyPreAssignedResources($invitation->metadata, $employee);
            } finally {
                $this->tenantManager->resetToPrevious();
            }

            $invitation->accepted_at = $acceptedAt;
            $invitation->save();

            return $employee;
        });
    }

    /**
     * Crée les assignations de ressources portées par l'invitation (R4).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function applyPreAssignedResources(?array $metadata, Employee $employee): void
    {
        $entries = $metadata['resource_assignments'] ?? null;
        if (! is_array($entries) || $entries === [] || $employee->company_id === null) {
            return;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = isset($entry['resource_type']) ? (string) $entry['resource_type'] : '';
            $resourceId = isset($entry['resource_id']) ? (int) $entry['resource_id'] : 0;
            $level = isset($entry['access_level']) ? (string) $entry['access_level'] : '';

            if ($type === '' || $resourceId <= 0 || EmployeeResourceAssignment::levelRank($level) < 0) {
                continue;
            }

            if (! $this->resourceTypes->exists($type, $resourceId, $employee->company_id)) {
                continue;
            }

            $existing = $employee->resourceAssignments()
                ->where('resource_type', $type)
                ->where('resource_id', $resourceId)
                ->first();

            if ($existing !== null) {
                if ($existing->access_level !== $level) {
                    $existing->access_level = $level;
                    $existing->save();
                }

                continue;
            }

            $assignment = new EmployeeResourceAssignment([
                'employee_id' => $employee->id,
                'resource_type' => $type,
                'resource_id' => $resourceId,
                'access_level' => $level,
            ]);
            $assignment->company_id = $employee->company_id;
            $assignment->save();
        }
    }
}
