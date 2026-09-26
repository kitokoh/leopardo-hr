<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AccountLocked;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #8163 (suite #8124) — notifie le TITULAIRE quand son compte est verrouillé
 * après des échecs de connexion (« votre compte a été verrouillé après des
 * échecs de connexion »).
 *
 * Canal canonique `NotificationDispatcher` (store `notifications` lu par
 * `GET /notifications` + push FCM best-effort, ADR-0013) — aucun nouvel
 * émetteur legacy. Le titulaire peut à nouveau se connecter avec ses
 * identifiants valides (#8124) : la notification in-app est donc réellement
 * visible, et le push l'est même sans connexion.
 *
 * L'envoi ne doit JAMAIS casser le parcours de login (try/catch + trace
 * structurée, doctrine NotifyTaxRateValidation / UserInvitationService).
 * Messages via le catalogue i18n `auth.php` (PA2-I18N-007).
 *
 * Méthode nommée `notify` (PAS `handle*`) + enregistrement explicite
 * `Class@notify` dans EventServiceProvider : la découverte automatique
 * Laravel 12 mappe TOUTES les méthodes publiques `handle*` typées
 * (`DiscoverEvents`), donc un `handle()`/`handleAccountLocked()` serait
 * enregistré DEUX fois (découverte + `$listen`) → double notification.
 * Dette préexistante constatée sur `SendInvoicePaymentReceipt`,
 * `CreateCrmLeadFromCatalogInquiry` et `NotifyTaxRateValidation`
 * (doublement enregistrés aujourd'hui) — ne pas la reproduire ici.
 */
class NotifyAccountLocked
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function notify(AccountLocked $event): void
    {
        try {
            $this->dispatcher->dispatch(
                $event->employee->id,
                'security.account_locked',
                (string) __('auth.account_locked_notify_title'),
                (string) __('auth.account_locked_notify_body', [
                    'minutes' => max(1, (int) ceil(now()->diffInMinutes($event->lockedUntil, false))),
                ]),
                [
                    'locked_until' => $event->lockedUntil->toIso8601String(),
                    'failed_attempts' => $event->failedAttempts,
                ],
            );
        } catch (Throwable $exception) {
            // Best-effort : le verrou reste posé, le parcours de login n'est
            // jamais interrompu par un échec de notification — mais la trace
            // est exploitable (identifiants uniquement, aucune PII).
            Log::channel('structured')->warning('auth.account_locked_notification_failed', [
                'employee_id' => $event->employee->id,
                'company_id' => $event->employee->company_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
