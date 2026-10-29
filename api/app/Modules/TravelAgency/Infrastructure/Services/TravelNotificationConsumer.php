<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationConsent;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationLog;
use App\Shared\Contracts\Notification\EmployeeNotifier;
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
        // TRAVEL-415 : l'annulation d'un trajet doit notifier TOUS les
        // voyageurs consentants (remboursement, repercutement). Sans cette
        // entree, l'evenement tombait en dead-letter `no_consumer` et les
        // clients apprenaient l'annulation a l'embarquement.
        'travel.trip.cancelled.v1',
    ];

    public function __construct(
        private readonly EmployeeNotifier $communicationService,
    ) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED_EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, array $payload): void
    {
        $eventId = isset($payload['event_id']) ? (int) $payload['event_id'] : null;

        // TRAVEL-415 : `travel.trip.cancelled.v1` ne porte pas de
        // `booking_reference` (c'est un evenement de trajet) : il faut
        // notifier tous les voyageurs consentants du trajet.
        if ($eventType === 'travel.trip.cancelled.v1') {
            $this->handleTripCancellation($payload, $eventId);

            return;
        }

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
        $this->notifyBooker($booking, $eventType, $payload);

        // Email externe : uniquement si consentement explicite (registre
        // travel_customer_contacts). Échec → transitoire (retry backoff).
        $this->notifyExternalContact($booking, $eventType, $eventId, $payload);
    }

    /**
     * Annulation de trajet : notifie tous les voyageurs du trajet disposant
     * d'un consentement mail actif (TRAVEL-415).
     *
     * Le motif est repris dans le message : c'est l'information la plus utile
     * pour le voyageur, et le call-center n'a pas a la repeter.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleTripCancellation(array $payload, ?int $eventId): void
    {
        $tripId = $payload['trip_id'] ?? null;

        if ($tripId === null) {
            throw new PermanentOutboxException('payload sans trip_id');
        }

        $bookings = TravelBooking::query()
            ->where('trip_id', $tripId)
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::CONFIRMED])
            ->get();

        foreach ($bookings as $booking) {
            $this->notifyExternalContact($booking, 'travel.trip.cancelled.v1', $eventId, $payload);
        }
    }

    /**
     * Écrit une ligne du journal d'audit RGPD (TRAVEL-415). Best-effort : une
     * panne du journal ne doit pas annuler une notification déjà délivrée.
     *
     * @param  array<string, mixed>  $payload
     */
    private function log(
        ?string $contactIdentifier,
        string $channel,
        string $status,
        ?string $reason,
        string $eventType,
        ?int $eventId,
        array $payload = [],
    ): void {
        // `contact_identifier` est NOT NULL en base : un contact sans email
        // connu est journalise sous une sentinelle explicite plutot que de
        // faire echouer l'insert (et d'abort la transaction PostgreSQL
        // englobante — 25P02, qui cassait ensuite TOUS les consommateurs
        // suivants du meme evenement).
        $identifier = $contactIdentifier !== null && $contactIdentifier !== ''
            ? $contactIdentifier
            : 'unknown';

        try {
            TravelNotificationLog::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'contact_identifier' => $identifier,
                'channel' => $channel,
                'status' => $status,
                'reason' => $reason,
                'payload_redacted' => $payload,
            ]);
        } catch (Throwable $e) {
            Log::channel('structured')->warning('travel.notification.log-failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyBooker(TravelBooking $booking, string $eventType, array $payload = []): void
    {
        if ($booking->booked_by_user_id === null) {
            return;
        }

        $employee = Employee::query()->find($booking->booked_by_user_id);

        if (! $employee instanceof Employee) {
            return;
        }

        [$template, $title] = $this->templateFor($eventType, $payload);

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyExternalContact(TravelBooking $booking, string $eventType, ?int $eventId, array $payload = []): void
    {
        // Source de verite du consentement : le registre dedie
        // `travel_notification_consents` (TRAVEL-415), par (contact, canal),
        // revocable. Le consommateur lisait auparavant `TravelCustomerContact`
        // uniquement — qui n'est alimente que par les formulaires de contact :
        // un client ayant donne son accord en boutique n'etait donc JAMAIS
        // notifie, alors que le registre portait bien son opt-in.
        $email = $booking->contact_email
            ?? ($booking->customer_contact_id === null
                ? null
                : TravelCustomerContact::query()->find($booking->customer_contact_id)?->email);

        $consentGranted = $email !== null && TravelNotificationConsent::query()
            ->where('contact_identifier', $email)
            ->where('channel', TravelNotificationConsent::CHANNEL_MAIL)
            ->whereNull('revoked_at')
            ->exists();

        if ($email === null || ! $consentGranted) {
            $reason = $email === null ? 'no_contact' : 'no_email_consent';

            $this->log(
                $email,
                TravelNotificationConsent::CHANNEL_MAIL,
                TravelNotificationLog::STATUS_SKIPPED,
                $reason,
                $eventType,
                $eventId,
                ['reason' => $reason],
            );

            Log::channel('structured')->info('travel.notification.external-skipped', [
                'booking_reference' => $booking->reference,
                'reason' => $reason,
            ]);

            return;
        }

        // Critere d'acceptation TRAVEL-415 : « aucune notification sans canal
        // configure ET consentement ». Chaque canal actif est traite
        // separement : un canal non configure est journalise `skipped`, jamais
        // silencieusement ignore (le support doit pouvoir repondre « pourquoi
        // je n'ai pas recu de WhatsApp ? »).
        $enabledChannels = (array) config('travel.notifications.enabled_channels', ['mail']);

        if (! in_array(TravelNotificationConsent::CHANNEL_MAIL, $enabledChannels, true)) {
            $this->log(
                $email,
                TravelNotificationConsent::CHANNEL_MAIL,
                TravelNotificationLog::STATUS_SKIPPED,
                'channel_not_configured',
                $eventType,
                $eventId,
                ['reason' => 'channel_not_configured'],
            );
        } else {
            $this->sendMail($booking, $email, $eventType, $eventId, $payload);
        }

        // WhatsApp / SMS : deux conditions distinctes pour « configure ».
        //   1. le canal est liste dans `enabled_channels` ;
        //   2. la passerelle est reellement provisionnee (identifiants).
        // Un canal liste mais sans identifiants (staging, tenant non migre)
        // doit produire une ligne `skipped` motivee, pas une exception ni un
        // silence : c'est ce que le support consultera.
        //
        // Le consentement de ces canaux est cle par le TELEPHONE
        // (contact_identifier), pas par l'email.
        $phone = $booking->contact_phone
            ?? ($booking->customer_contact_id === null
                ? null
                : TravelCustomerContact::query()->find($booking->customer_contact_id)?->phone);

        $identifiers = array_values(array_filter([$phone, $email]));

        foreach ([TravelNotificationConsent::CHANNEL_WHATSAPP, TravelNotificationConsent::CHANNEL_SMS] as $channel) {
            if ($identifiers === []) {
                break;
            }

            $hasConsent = TravelNotificationConsent::query()
                ->whereIn('contact_identifier', $identifiers)
                ->where('channel', $channel)
                ->whereNull('revoked_at')
                ->exists();

            if (! $hasConsent) {
                continue;
            }

            if ($this->isChannelProvisioned($channel, $enabledChannels)) {
                // Passerelle prete mais envoi non implemente pour ce canal :
                // on trace explicitement plutot que de faire croire a un envoi.
                $this->log(
                    $identifiers[0],
                    $channel,
                    TravelNotificationLog::STATUS_SKIPPED,
                    'channel_send_not_implemented',
                    $eventType,
                    $eventId,
                    ['reason' => 'channel_send_not_implemented'],
                );

                continue;
            }

            $this->log(
                $identifiers[0],
                $channel,
                TravelNotificationLog::STATUS_SKIPPED,
                'channel_not_configured',
                $eventType,
                $eventId,
                ['reason' => 'channel_not_configured'],
            );
        }
    }

    /**
     * Un canal est « configure » s'il est active ET que sa passerelle est
     * provisionnee. `enabled_channels` seul est une intention de configuration.
     *
     * @param  array<int, string>  $enabledChannels
     */
    private function isChannelProvisioned(string $channel, array $enabledChannels): bool
    {
        if (! in_array($channel, $enabledChannels, true)) {
            return false;
        }

        return match ($channel) {
            TravelNotificationConsent::CHANNEL_WHATSAPP => (string) config('travel.notifications.whatsapp_phone_number_id', '') !== ''
                && (string) config('travel.notifications.whatsapp_access_token', '') !== '',
            TravelNotificationConsent::CHANNEL_SMS => (string) config('travel.notifications.sms_from', '') !== '',
            default => false,
        };
    }

    /**
     * Envoi du mail + journalisation (sent / failed).
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendMail(
        TravelBooking $booking,
        string $email,
        string $eventType,
        ?int $eventId,
        array $payload,
    ): void {
        [$template, $title] = $this->templateFor($eventType, $payload);

        try {
            Mail::to($email)->send(new CommunicationMail(
                $title,
                $title.' — '.$booking->reference,
                null,
            ));

            $this->log(
                $email,
                TravelNotificationConsent::CHANNEL_MAIL,
                TravelNotificationLog::STATUS_SENT,
                null,
                $eventType,
                $eventId,
                ['template' => $template],
            );

            Log::channel('structured')->info('travel.notification.email-sent', [
                'booking_reference' => $booking->reference,
                'template' => $template,
                'contact_email' => $email,
            ]);
        } catch (Throwable $e) {
            $this->log(
                $email,
                TravelNotificationConsent::CHANNEL_MAIL,
                TravelNotificationLog::STATUS_FAILED,
                $e->getMessage(),
                $eventType,
                $eventId,
            );

            throw new TransientOutboxException('échec envoi email: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string} [templateKey, title]
     */
    private function templateFor(string $eventType, array $payload = []): array
    {
        // TRAVEL-415 : un voyageur non francophone doit recevoir un message
        // dans SA langue. Sans cela, un client anglophone recevait un titre
        // français et contactait le support pour comprendre l'annulation.
        $locale = isset($payload['locale']) ? (string) $payload['locale'] : 'fr';
        $english = str_starts_with(strtolower($locale), 'en');

        // Le libellé suit l'ÉVÉNEMENT, pas seulement le statut courant : un
        // client recevant `booking.confirmed.v1` doit lire « confirmé » même
        // si la réservation n'est pas encore marquée payée en base (course
        // normale entre le paiement et la projection).
        $cancelled = str_contains($eventType, 'cancelled');
        $confirmed = str_contains($eventType, 'confirmed');

        if ($english) {
            if ($cancelled) {
                return ['travel_booking_cancelled', 'Booking cancelled'];
            }

            if ($confirmed) {
                return ['travel_booking_paid', 'Booking confirmed'];
            }

            return ['travel_booking_status', 'Your booking has been updated'];
        }

        if ($cancelled) {
            return ['travel_booking_cancelled', 'Réservation annulée'];
        }

        if ($confirmed) {
            return ['travel_booking_paid', 'Réservation confirmée'];
        }

        return ['travel_booking_status', 'Mise à jour de votre réservation'];
    }
}
