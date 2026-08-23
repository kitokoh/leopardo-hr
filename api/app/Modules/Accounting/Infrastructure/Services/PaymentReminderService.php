<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPaymentReminder;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Relances de paiement automatiques (issue #5229) — stages J+7 / J+15 / J+30
 * paramétrables par entreprise (`accounting_settings.payment_reminder_days`).
 *
 * Règles :
 *   - cibles : documents émis non soldés (invoice/credit_note, statut
 *     sent/partially_paid/overdue) dont `due_date` est dépassée d'au moins le
 *     nombre de jours du stage ;
 *   - une relance = une ligne `accounting_payment_reminders` (unique
 *     company+document+stage) → zéro doublon, même en double exécution ;
 *   - destinataires : managers principal + comptable du tenant
 *     (CommunicationService — préférences de canaux respectées) ;
 *   - un échec de notification ne fait jamais échouer la relance (log).
 */
final class PaymentReminderService
{
    public const DEFAULT_REMINDER_DAYS = [7, 15, 30];

    public function __construct(private readonly CommunicationService $communication) {}

    /**
     * Délais configurés (jours) pour l'entreprise courante.
     *
     * @return list<int>
     */
    public function reminderDays(): array
    {
        $settings = AccountingSettings::query()->first();
        $days = is_array($settings?->payment_reminder_days) ? $settings->payment_reminder_days : [];

        $normalized = [];
        foreach ($days as $day) {
            $value = (int) $day;
            if ($value > 0 && ! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? $normalized : self::DEFAULT_REMINDER_DAYS;
    }

    /**
     * Exécute les relances dues. Retourne le nombre de relances envoyées.
     */
    public function run(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $days = $this->reminderDays();
        $sent = 0;

        foreach ($days as $index => $day) {
            $stage = $index + 1;
            $cutoff = $now->copy()->subDays($day);

            /** @var list<AccountingDocument> $documents */
            $documents = AccountingDocument::query()
                ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
                ->whereNotNull('due_date')
                ->where('due_date', '<=', $cutoff->toDateString())
                ->whereColumn('paid_amount', '<', 'total_ttc')
                ->get()
                ->all();

            foreach ($documents as $document) {
                $alreadySent = AccountingPaymentReminder::query()
                    ->where('document_id', $document->id)
                    ->where('stage', $stage)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $reminder = AccountingPaymentReminder::query()->create([
                    'document_id' => $document->id,
                    'stage' => $stage,
                    'sent_at' => $now,
                ]);

                $this->notifyManagers($document, $day, $stage, $reminder);
                ++$sent;
            }
        }

        return $sent;
    }

    private function notifyManagers(AccountingDocument $document, int $day, int $stage, AccountingPaymentReminder $reminder): void
    {
        $managers = Employee::query()
            ->whereIn('manager_role', ['principal', 'comptable'])
            ->get();

        foreach ($managers as $manager) {
            try {
                $this->communication->notifyEmployee($manager, 'accounting_payment_reminder', [
                    'document_number' => (string) $document->number,
                    'document_type' => (string) $document->type,
                    'document_total' => (string) $document->total_ttc,
                    'due_date' => $document->due_date?->toDateString() ?? '',
                    'days_overdue' => (string) $day,
                    'reminder_stage' => (string) $stage,
                ]);
            } catch (Throwable $exception) {
                Log::warning('accounting: payment reminder notification failed', [
                    'document_id' => $document->id,
                    'reminder_id' => $reminder->id,
                    'manager_id' => $manager->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
