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
        // Contrat du listener (docblock) : les notifications/emails ne doivent
        // JAMAIS casser la transition — toute erreur (table de notifications
        // absente sur un environnement partiel, SMTP down, etc.) est journalisée
        // et avalée (écart 1 #1923 : le listener était mort ; en l'activant,
        // on le blinde pour qu'il reste best-effort).
        try {
            $this->doHandleSubmitted($event);
        } catch (\Throwable $e) {
            Log::warning('tax-rate.notification-failed', [
                'event' => 'submitted',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function doHandleSubmitted(TaxRateSubmitted $event): void
    {
        $label = $this->label($event->model);
        $title = __('payroll.rate_notif_title_submitted', ['label' => $label]);

        Log::info('tax-rate.submitted', [
            'table' => $event->model->getTable(),
            'record_id' => $event->model->getKey(),
            'actor_id' => $event->actor->id,
        ]);

        foreach (SuperAdmin::query()->get() as $admin) {
            $this->emailBestEffort($admin->email, $title, __('payroll.rate_notif_body_submitted', [
                'kind' => $event->model instanceof TaxSlab
                    ? __('payroll.rate_notif_kind_slab')
                    : __('payroll.rate_notif_kind_contribution'),
                'label' => $label,
            ]));
        }
    }

    public function handleTaxRateApproved(TaxRateApproved $event): void
    {
        try {
            $this->notifySubmitter(
                $event->model,
                __('payroll.rate_notif_verb_approved'),
                __('payroll.rate_notif_body_approved', ['label' => $this->label($event->model)]),
            );
        } catch (\Throwable $e) {
            Log::warning('tax-rate.notification-failed', [
                'event' => 'approved',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleTaxRateRejected(TaxRateRejected $event): void
    {
        try {
            $this->notifySubmitter(
                $event->model,
                __('payroll.rate_notif_verb_rejected'),
                __('payroll.rate_notif_body_rejected', [
                    'label' => $this->label($event->model),
                    'reason' => $event->reason,
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('tax-rate.notification-failed', [
                'event' => 'rejected',
                'error' => $e->getMessage(),
            ]);
        }
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
                __('payroll.rate_notif_subject', ['verb' => $verb]),
                $body,
                ['table' => $model->getTable(), 'record_id' => $model->getKey()],
            );
        } catch (\Throwable $e) {
            Log::warning('tax-rate.notification-inapp-failed', [
                'employee_id' => $submitter->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->emailBestEffort($submitter->email, __('payroll.rate_notif_subject', ['verb' => $verb]), $body);
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
