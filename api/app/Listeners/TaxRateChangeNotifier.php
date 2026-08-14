<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\SocialContributionApproved;
use App\Events\SocialContributionRejected;
use App\Events\SocialContributionSubmittedForValidation;
use App\Events\TaxSlabApproved;
use App\Events\TaxSlabRejected;
use App\Events\TaxSlabSubmittedForValidation;
use App\Mail\CommunicationMail;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ADMIN-PAIE (#1813) — notifications du workflow de validation des taux
 * légaux :
 *   - soumission (draft → pending) : email aux platform_admins ;
 *   - approbation / rejet : notification in-app + email au soumissionnaire.
 *
 * Les échecs de notification ne cassent JAMAIS la transition métier :
 * tout est encapsulé dans try/catch + report().
 */
class TaxRateChangeNotifier implements ShouldQueue
{
    public function __construct(
        private readonly CommunicationService $communication,
    ) {}

    public function handle(object $event): void
    {
        try {
            match (true) {
                $event instanceof TaxSlabSubmittedForValidation => $this->notifyAdminsOfSubmission('barème fiscal', $event->taxSlab->country_code, $event->taxSlab->name, $event->taxSlab->id),
                $event instanceof SocialContributionSubmittedForValidation => $this->notifyAdminsOfSubmission('cotisation sociale', $event->socialContribution->country_code, $event->socialContribution->name, $event->socialContribution->id),
                $event instanceof TaxSlabApproved => $this->notifySubmittingEmployee($event->submittedBy, 'tax_rate_approved', ['rate_name' => $event->taxSlab->name, 'country' => $event->taxSlab->country_code]),
                $event instanceof TaxSlabRejected => $this->notifySubmittingEmployee($event->submittedBy, 'tax_rate_rejected', ['rate_name' => $event->taxSlab->name, 'country' => $event->taxSlab->country_code, 'reason' => $event->reason]),
                $event instanceof SocialContributionApproved => $this->notifySubmittingEmployee($event->submittedBy, 'tax_rate_approved', ['rate_name' => $event->socialContribution->name, 'country' => $event->socialContribution->country_code]),
                $event instanceof SocialContributionRejected => $this->notifySubmittingEmployee($event->submittedBy, 'tax_rate_rejected', ['rate_name' => $event->socialContribution->name, 'country' => $event->socialContribution->country_code, 'reason' => $event->reason]),
                default => null,
            };
        } catch (Throwable $throwable) {
            Log::error('TaxRateChangeNotifier failed', ['exception' => $throwable]);
        }
    }

    private function notifyAdminsOfSubmission(string $type, string $countryCode, string $name, int $recordId): void
    {
        $subject = "Validation requise — modification de {$type} ({$countryCode})";
        $body = "Une modification de {$type} « {$name} » (#{$recordId}, {$countryCode}) attend votre approbation "
            .'dans le tableau de bord plateforme (Paie → Taux légaux).';

        foreach (SuperAdmin::query()->get() as $superAdmin) {
            Mail::to($superAdmin->email)->send(new CommunicationMail($subject, $body));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function notifySubmittingEmployee(int $employeeId, string $templateKey, array $context): void
    {
        /** @var Employee|null $employee */
        $employee = Employee::query()->find($employeeId);

        if ($employee === null) {
            return;
        }

        $this->communication->notifyEmployee($employee, $templateKey, $context);
    }
}
