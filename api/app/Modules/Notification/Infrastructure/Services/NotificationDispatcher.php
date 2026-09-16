<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * #7481 — UNE SEULE source de vérité pour la notification in-app : `notifications`.
 *
 * Constat de la dette : deux stores coexistaient, alimentés séparément —
 * `notifications` (lu par `GET /notifications`, donc par le web et le mobile,
 * écrit par `CommunicationService` qui applique préférences, heures calmes,
 * quotas et audit) et `app_notifications` (écrit ici même). Conséquences
 * mesurées : une notification produite par ce dispatcher était **invisible**
 * pour tout client (l'API ne lit pas cette table), **non filtrée** par les
 * préférences et les heures calmes, **non comptée** dans les quotas, et
 * **absente de l'audit** (`CommunicationEvent`) — un canal de notification
 * parallèle, hors politique.
 *
 * Le dispatcher écrit donc désormais la ligne canonique, avec la même forme
 * que `CommunicationService` (canal `app`) : `employee_id`, `company_id`,
 * `type`, `title`, `body`, `data` (l'`action_url` y est conservée sous la clé
 * `action_url`, ce qui évite une colonne dédiée), `is_read = false`.
 *
 * Ce que ce chemin NE fait toujours pas (et pourquoi c'est assumé) : les
 * préférences, les heures calmes, les quotas et l'audit par canal vivent dans
 * `CommunicationService`, qui reste la porte d'entrée produit. Ce dispatcher
 * est le transport direct utilisé par les appelants qui n'ont pas de gabarit
 * (ex. validation de taux de paie, port `InAppNotifier`) : il publie dans la
 * boîte de réception unique, sans dupliquer la politique.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    public function dispatch(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
    ): Notification {
        // Issue #2498 — la dette #2398 (table `app_notifications` jamais
        // migrée en prod) a rendu les échecs de création in-app totalement
        // invisibles : les try/catch best-effort des appelants loggaient à
        // peine. Journalisation structurée systématique (channel `structured`)
        // sur TOUT échec, sans changer le contrat best-effort (on relance :
        // l'appelant garde son propre comportement, mais la trace est
        // désormais exploitable).
        try {
            /** @var Employee|null $employee */
            $employee = Employee::query()->find($userId);

            if (! $employee instanceof Employee) {
                throw new RuntimeException("Destinataire introuvable : employé {$userId}.");
            }

            $notification = Notification::query()->create([
                'company_id' => (string) $employee->company_id,
                'employee_id' => $employee->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                // `action_url` n'a pas de colonne dédiée dans le store
                // canonique : elle voyage dans `data` (même convention que les
                // métadonnées de `CommunicationService`).
                'data' => $actionUrl !== null ? $data + ['action_url' => $actionUrl] : $data,
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('structured')->error('notification.inapp-create-failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Push mobile (FCM) — best-effort : un échec de push ne doit jamais
        // casser la notification in-app (fail-open, journalisé structuré).
        try {
            $this->pushService->sendToUser($userId, $title, (string) $body, $data);
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('notification.push-skipped', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }

        return $notification;
    }
}
