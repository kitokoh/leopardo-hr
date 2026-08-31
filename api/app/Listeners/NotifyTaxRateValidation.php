<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\TenantManager;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Modules\Notification\Application\Actions\SendNotification;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Support\PlatformCompanyLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
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
 * Issue #1923 (revue lead) :
 * - Le listener est ENFIN enregistré (EventServiceProvider, notation
 *   `Class@handleTaxRateSubmitted|Approved|Rejected` — l'event discovery
 *   étant désactivée dans ce repo, les méthodes handleTaxRate* n'étaient
 *   jamais appelées) ;
 * - Tous les messages passent par le catalogue `api/lang/{fr,en,ar,tr}/payroll.php`
 *   (PA2-I18N-007, plus de chaînes accentuées en dur) ;
 * - La notification in-app approbation/rejet résout le tenant du
 *   soumissionnaire via le company_id de la ligne (le contexte admin n'a pas
 *   de search_path tenant) : `TenantManager::withinTenant()` + lookup
 *   `public.companies` (Pattern PlatformCompanyLookup, #1994).
 *
 * Les envois email ne doivent JAMAIS casser la transition (try/catch +
 * report, pattern UserInvitationService #1776).
 */
class NotifyTaxRateValidation
{
    public function __construct(
        private readonly SendNotification $sendNotification,
        private readonly TenantManager $tenantManager,
    ) {}

    public function handleTaxRateSubmitted(TaxRateSubmitted $event): void
    {
        $label = $this->label($event->model);
        $title = __('payroll.rate_validation_requested_title', ['label' => $label]);
        $kind = $event->model instanceof TaxSlab
            ? __('payroll.rate_kind_tax_scale')
            : __('payroll.rate_kind_contribution');

        Log::info('tax-rate.submitted', [
            'table' => $event->model->getTable(),
            'record_id' => $event->model->getKey(),
            'actor_id' => $event->actor->id,
        ]);

        foreach (SuperAdmin::query()->get() as $admin) {
            $this->emailBestEffort($admin->email, $title, __('payroll.rate_validation_requested_body', [
                'kind' => $kind,
                'label' => $label,
            ]));
        }
    }

    public function handleTaxRateApproved(TaxRateApproved $event): void
    {
        $this->notifySubmitter(
            $event->model,
            TaxRateApproved::class,
            __('payroll.rate_approved_title'),
            __('payroll.rate_approved_body', ['label' => $this->label($event->model)]),
        );
    }

    public function handleTaxRateRejected(TaxRateRejected $event): void
    {
        $this->notifySubmitter(
            $event->model,
            TaxRateRejected::class,
            __('payroll.rate_rejected_title'),
            __('payroll.rate_rejected_body', [
                'label' => $this->label($event->model),
                'reason' => $event->reason,
            ]),
        );
    }

    /**
     * Notifie l'auteur de la soumission (comptable/principal) : notification
     * in-app dans le schéma tenant + email best-effort.
     *
     * Issue #1923 — la transition approbation/rejet s'exécute dans le
     * contexte platform_admin (aucun search_path tenant) : le tenant du
     * soumissionnaire est résolu depuis le `company_id` de la ligne
     * (`public.companies`, pattern PlatformCompanyLookup) et le traitement
     * in-app s'exécute via `TenantManager::withinTenant()`.
     */
    private function notifySubmitter(Model $model, string $event, string $title, string $body): void
    {
        /** @var int|null $submittedBy */
        $submittedBy = $model->submitted_by ?? null;
        if ($submittedBy === null || $model->getAttribute('company_id') === null) {
            return;
        }

        // Issue #1923 — le contexte admin n'a pas de TenantMiddleware pour
        // restaurer le search_path : on sauvegarde l'état AVANT le lookup
        // public + le scope tenant, et on le restaure dans tous les cas
        // (sinon la requête suivante résoudrait les tables dans le mauvais
        // schéma — pattern TenantMiddleware, même `SHOW search_path`).
        $previousSearchPath = DB::getDriverName() === 'pgsql'
            ? DB::scalar('SHOW search_path')
            : null;

        try {
            try {
                $company = PlatformCompanyLookup::findOrFail((string) $model->getAttribute('company_id'));
            } catch (\Throwable $e) {
                // Issue #2498 — observabilité structurée (channel `structured`) :
                // un échec de résolution du tenant doit être traçable.
                Log::channel('structured')->warning('tax-rate.submitter-company-lookup-failed', [
                    'event'      => $event,
                    'company_id' => $model->getAttribute('company_id'),
                    'error'      => $e->getMessage(),
                ]);

                return;
            }

            $this->tenantManager->withinTenant($company, function () use ($submittedBy, $event, $title, $body, $model): void {
                $submitter = Employee::query()->find($submittedBy);
                if ($submitter === null) {
                    return;
                }

                try {
                    $this->sendNotification->execute(
                        (int) $submitter->id,
                        'tax_rate_validation',
                        $title,
                        $body,
                        ['table' => $model->getTable(), 'record_id' => $model->getKey()],
                    );
                } catch (\Throwable $e) {
                    // Issue #2498 — échec du dispatch in-app : contexte complet
                    // (événement, type de notification, destinataire, erreur).
                    Log::channel('structured')->warning('tax-rate.notification-inapp-failed', [
                        'event'      => $event,
                        'type'       => 'tax_rate_validation',
                        'user_id'    => $submitter->id,
                        'company_id' => $model->getAttribute('company_id'),
                        'error'      => $e->getMessage(),
                    ]);
                }

                $this->emailBestEffort($submitter->email, $title, $body);
            });
        } catch (\Throwable $e) {
            Log::channel('structured')->warning('tax-rate.notification-tenant-failed', [
                'event'      => $event,
                'company_id' => $model->getAttribute('company_id'),
                'error'      => $e->getMessage(),
            ]);
        } finally {
            if (is_string($previousSearchPath) && $previousSearchPath !== '') {
                DB::statement('SET search_path TO '.$previousSearchPath);
            }
        }
    }

    private function emailBestEffort(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function (Message $message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
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
