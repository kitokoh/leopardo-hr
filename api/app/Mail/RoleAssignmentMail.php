<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

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
        // Queued mail (Mail::to()->queue()) — runs outside the HTTP request
        // lifecycle, so the SetLocale middleware never applies here. Resolve
        // the recipient's preferred language explicitly before building any
        // translated strings (role label, subject, view).
        $this->locale = I18nCatalog::normalizeLocale(
            $employee->preferred_language ?? $company->language
        );
        App::setLocale($this->locale);

        $this->appLinks = RoleInvitationService::getAppDownloadLink('manager', $newManagerRole);
        $this->roleLabel = RoleInvitationService::getRoleLabel($newManagerRole);
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        return $this
            ->subject(__('emails.role_assignment_subject', ['role' => $this->roleLabel]))
            ->view('emails.role-assignment');
    }
}

