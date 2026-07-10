<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RoleAssignmentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly array $appLinks;
    public readonly string $roleLabel;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $employee,
        public readonly string $assignedByName,
        public readonly string $newManagerRole,
    ) {
        $this->appLinks = RoleInvitationService::getAppDownloadLink('manager', $newManagerRole);
        $this->roleLabel = RoleInvitationService::getRoleLabel($newManagerRole);
    }

    public function build(): self
    {
        return $this
            ->subject("Leopardo RH — Vous avez été nommé {$this->roleLabel}")
            ->view('emails.role-assignment');
    }
}

