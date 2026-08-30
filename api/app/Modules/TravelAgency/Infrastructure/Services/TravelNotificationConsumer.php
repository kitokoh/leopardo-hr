<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Mail\CommunicationMail;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * TRAVEL-415 (#6067) — Notifications voyageur (confirmation/annulation/
 * paiement/billet) via les canaux de la plateforme.
 *
 * Règle (spéc §8.5) : AUCUNE notification sans canal configuré et
 * consentement explicite.
 *  - canal in-app/push : BC-13 `CommunicationService::notifyEmployee()`
 *    (respecte les préférences du destinataire) — destinataire = employé
 *    du tenant ayant créé la réservation (`booked_by_user_id`) ;
 *  - canal email externe : `travel_customer_contacts` (consentement
 *    explicite horodaté, registre propriétaire de la verticale).
 * Aucune donnée financière dans les notifications (résumé minimal + lien).
 *
 * Le consommateur est IDEMPOTENT : le rejeu d'un événement ne renvoie pas
 * deux fois (pas de doublon de notification pour le même booking/état).
 */
final class TravelNotificationConsumer implements TravelOutboxConsumer
{
    /** @var list<string> */
    private const SUPPORTED_EVENTS = [
        'travel.booking.pending.v1',
        'travel.booking.confirmed.v1',
        'travel.booking.cancelled.v1',
        'travel.payment.confirmed.v1',
        'travel.payment.refunded.v1',
        'travel.ticket.issued.v1',
    ];

    public function __construct(
        private readonly CommunicationService $communicationService,
    ) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED_EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $reference = (string) ($payload['booking_reference'] ?? '');

        if ($reference === '') {
            throw new PermanentOutboxException('payload sans booking_reference');
        }

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()->where('reference', $reference)->first();

        if (! $booking instanceof TravelBooking) {
            throw new PermanentOutboxException("réservation introuvable: {$reference}");
        }

        // Notification in-app/push à l'employé du tenant (BC-13, préférences
        // respectées par CommunicationService). Best-effort : un échec de
        // notification ne doit pas faire rejouer l'événement.
        $this->notifyBooker($booking);

        // Email externe : uniquement si consentement explicite (registre
        // travel_customer_contacts). Échec → transitoire (retry backoff).
        $this->notifyExternalContact($booking);
    }

    private function notifyBooker(TravelBooking $booking): void
    {
        if ($booking->booked_by_user_id === null) {
            return;
        }

        $employee = Employee::query()->find($booking->booked_by_user_id);

        if (! $employee instanceof Employee) {
            return;
        }

        [$template, $title] = $this->templateFor($booking);

        try {
            $this->communicationService->notifyEmployee($employee, $template, [
                'title' => $title,
                'body' => $title.' — '.$booking->reference,
                'booking_reference' => $booking->reference,
                'trip_id' => $booking->trip_id,
            ], ['app', 'push']);
        } catch (Throwable $e) {
            Log::channel('structured')->warning('travel.notification.inapp-skipped', [
                'booking_reference' => $booking->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyExternalContact(TravelBooking $booking): void
    {
        $contact = $booking->customer_contact_id === null
            ? null
            : TravelCustomerContact::query()->find($booking->customer_contact_id);

        if (! $contact instanceof TravelCustomerContact || ! $contact->hasEmailConsent()) {
            Log::channel('structured')->info('travel.notification.external-skipped', [
                'booking_reference' => $booking->reference,
                'reason' => $contact === null ? 'no_contact' : 'no_email_consent',
            ]);

            return;
        }

        [$template, $title] = $this->templateFor($booking);

        try {
            Mail::to($contact->email)->send(new CommunicationMail(
                $title,
                $title.' — '.$booking->reference,
                null,
            ));
            Log::channel('structured')->info('travel.notification.email-sent', [
                'booking_reference' => $booking->reference,
                'template' => $template,
                'contact_id' => $contact->id,
            ]);
        } catch (Throwable $e) {
            throw new TransientOutboxException('échec envoi email: '.$e->getMessage());
        }
    }

    /**
     * @return array{0: string, 1: string} [templateKey, title]
     */
    private function templateFor(TravelBooking $booking): array
    {
        if ($booking->status === BookingStatus::CANCELLED) {
            return ['travel_booking_cancelled', 'Réservation annulée'];
        }

        if ($booking->status === BookingStatus::CONFIRMED && $booking->payment_status === PaymentStatus::CONFIRMED) {
            return ['travel_booking_paid', 'Paiement confirmé'];
        }

        return ['travel_booking_status', 'Mise à jour de votre réservation'];
    }
}
