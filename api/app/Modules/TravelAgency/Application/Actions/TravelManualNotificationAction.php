<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Mail\CommunicationMail;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * TRAVEL-910 (#6113) — Notifications manuelles legacy gv-back → canaux
 * plateforme.
 *
 * Remplace la file « notifications maison » de gv-back : l'envoi manuel
 * d'un message à un contact passe par les canaux de la plateforme
 * (in-app BC-13 si le contact est lié à un employé, email transactionnel
 * externe sinon) et n'existe QUE si le consentement du canal est explicite
 * (registre `travel_customer_contacts`). Aucune table de notifications
 * maison n'est créée.
 *
 * @return array{channels: list<string>}
 */
final class TravelManualNotificationAction
{
    public function __construct(private readonly CommunicationService $communicationService) {}

    /**
     * @return array{channels: list<string>}
     */
    public function execute(
        TravelCustomerContact $contact,
        Employee $actor,
        string $message,
        array $channels = ['email'],
    ): array {
        $sent = [];

        if (in_array('email', $channels, true) && $contact->hasEmailConsent()) {
            try {
                Mail::to($contact->email)->send(new CommunicationMail(
                    'Message de votre agence de voyage',
                    $message,
                    null,
                ));
                $sent[] = 'email';
            } catch (Throwable $e) {
                Log::channel('structured')->error('travel.notification.manual-email-failed', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // In-app BC-13 : si le contact est lié à un employé du tenant
        // (metadata_json contient employee_id), on notifie via les
        // préférences BC-13 (jamais de contournement du consentement).
        $employeeId = is_array($contact->metadata_json)
            ? ($contact->metadata_json['employee_id'] ?? null)
            : null;

        if (in_array('app', $channels, true) && $employeeId !== null) {
            $employee = Employee::query()->find((int) $employeeId);

            if ($employee instanceof Employee && $employee->company_id === $actor->company_id) {
                $this->communicationService->notifyEmployee($employee, 'travel_manual_message', [
                    'title' => 'Message de votre agence de voyage',
                    'body' => $message,
                ], ['app']);
                $sent[] = 'app';
            }
        }

        if ($sent === []) {
            abort(422, 'Aucun canal configuré avec consentement pour ce contact.');
        }

        return ['channels' => $sent];
    }
}
