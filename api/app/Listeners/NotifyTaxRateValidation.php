<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Modules\Notification\Application\Actions\SendNotification;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Issue #1813 — notifications à chaque transition du workflow de validation
 * des taux légaux.
 *
 * - Soumission → email best-effort aux platform_admins (pas de canal in-app
 *   super-admin à ce jour : journalisation structurée en complément).
 * - Approbation/Rejet → notification in-app (AppNotification) + email
 *   best-effort au comptable/principal qui a soumis.
 *
 * Les envois email ne doivent JAMAIS casser la transition (try/catch +
 * report, pattern UserInvitationService #1776).
 */
class NotifyTaxRateValidation
{
    public function __construct(private readonly SendNotification $sendNotification) {}

    public function handleTaxRateSubmitted(TaxRateSubmitted $event): void
    {
        $label = $this->label($event->model);
        $title = sprintf('Validation de taux demandée — %s', $label);

        Log::info('tax-rate.submitted', [
            'table' => $event->model->getTable(),
            'record_id' => $event->model->getKey(),
            'actor_id' => $event->actor->id,
        ]);

        foreach (SuperAdmin::query()->get() as $admin) {
            $this->emailBestEffort($admin->email, $title, sprintf(
                'Un %s de taux légal (%s) attend votre validation dans l’interface admin.',
                $event->model instanceof TaxSlab ? 'barème fiscal' : 'taux de cotisation',
                $label,
            ));
        }
    }

    public function handleTaxRateApproved(TaxRateApproved $event): void
    {
        $this->notifySubmitter($event->model, 'approuvée', sprintf(
            'Votre modification de taux légal (%s) a été approuvée et est active.',
            $this->label($event->model),
        ));
    }

    public function handleTaxRateRejected(TaxRateRejected $event): void
    {
        $this->notifySubmitter($event->model, 'rejetée', sprintf(
            'Votre modification de taux légal (%s) a été rejetée : %s',
            $this->label($event->model),
            $event->reason,
        ));
    }

    /**
     * Notifie l'auteur de la soumission (comptable/principal) : notification
     * in-app dans le schéma tenant + email best-effort.
     */
    private function notifySubmitter(Model $model, string $verb, string $body): void
    {
        /** @var int|null $submittedBy */
        $submittedBy = $model->submitted_by ?? null;
        if ($submittedBy === null) {
            return;
        }

        $submitter = Employee::query()->find($submittedBy);
        if ($submitter === null) {
            return;
        }

        try {
            $this->sendNotification->handle(
                (int) $submitter->id,
                'tax_rate_validation',
                sprintf('Modification de taux %s', $verb),
                $body,
                ['table' => $model->getTable(), 'record_id' => $model->getKey()],
            );
        } catch (\Throwable $e) {
            Log::warning('tax-rate.notification-inapp-failed', [
                'employee_id' => $submitter->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->emailBestEffort($submitter->email, sprintf('Modification de taux %s', $verb), $body);
    }

    private function emailBestEffort(string $to, string $subject, string $body): void
    {
        try {
            /** @var PendingMail $pending */
            $pending = Mail::raw($body, function (Message $message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
            unset($pending);
        } catch (\Throwable $e) {
            // Mailer non configuré (MAIL_MAILER vide) ou transport en erreur :
            // la transition ne doit jamais casser pour un email (#1776).
            Log::info('tax-rate.email-skipped', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function label(Model $model): string
    {
        $key = $model->getKey();

        return sprintf('%s/%s', $model->getTable(), is_scalar($key) ? (string) $key : '');
    }
}
