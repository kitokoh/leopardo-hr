<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationConsent;
use App\Modules\TravelAgency\Domain\Models\TravelNotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * TRAVEL-415 (#6067) — Envoi des notifications voyageur.
 *
 * Règles (critère d'acceptation) :
 *   - aucun envoi sans canal configuré (`config('travel.notifications.enabled_channels')`)
 *     ET consentement actif (`travel_notification_consents`, révocable) ;
 *   - contenu minimal + lien authentifié de suivi, jamais de données
 *     financières dans WhatsApp (spec §8.5) ;
 *   - mail réel via le mailer Laravel ; SMS via Twilio REST ; WhatsApp via
 *     l'API Cloud de Meta — chaque canal non configuré est tracé `skipped`.
 * Toutes les tentatives sont journalisées (audit RGPD, payload redacted).
 */
final class TravelNotificationService
{
    /**
     * Déclenche les notifications d'un événement métier vers le contact de
     * la réservation (ou de toutes les réservations du trajet si l'événement
     * est au niveau trajet).
     *
     * @param  array<string, mixed>  $payload
     */
    public function notify(string $companyId, string $eventType, array $payload): void
    {
        $bookings = $this->resolveBookings($companyId, $eventType, $payload);

        foreach ($bookings as $booking) {
            $contact = $this->contactFor($booking);
            $locale = isset($payload['locale']) && is_string($payload['locale']) ? $payload['locale'] : 'fr';
            $template = $this->templateFor($eventType, $payload, $booking, $locale);

            if ($contact['email'] === null && $contact['phone'] === null) {
                $this->log($companyId, $payload, $eventType, 'n/a', 'n/a', 'skipped', 'Aucun contact sur la réservation.');

                continue;
            }

            if (! $booking->notify_consent) {
                $this->log($companyId, $payload, $eventType, $contact['email'] ?? $contact['phone'] ?? 'n/a', 'n/a', 'skipped', 'Consentement non accordé.');

                continue;
            }

            foreach ((array) config('travel.notifications.enabled_channels', []) as $channel) {
                $this->dispatchChannel($companyId, $eventType, $payload, $booking, $contact, $template, (string) $channel);
            }
        }
    }

    /**
     * @return list<TravelBooking>
     */
    private function resolveBookings(string $companyId, string $eventType, array $payload): array
    {
        $reference = isset($payload['booking_reference']) ? (string) $payload['booking_reference'] : null;

        if ($reference !== null && $reference !== '') {
            $booking = TravelBooking::query()
                ->where('company_id', $companyId)
                ->where('reference', $reference)
                ->first();

            return $booking instanceof TravelBooking ? [$booking] : [];
        }

        // Événement au niveau trajet (travel.trip.cancelled.v1) : notifier
        // les contacts de toutes les réservations impactées.
        $tripId = isset($payload['trip_id']) ? (int) $payload['trip_id'] : null;

        if ($tripId !== null) {
            /** @var list<TravelBooking> $bookings */
            $bookings = TravelBooking::query()
                ->where('company_id', $companyId)
                ->where('trip_id', $tripId)
                ->where('notify_consent', true)
                ->get()
                ->all();

            return $bookings;
        }

        return [];
    }

    /**
     * @return array{email: string|null, phone: string|null}
     */
    private function contactFor(TravelBooking $booking): array
    {
        $email = is_string($booking->contact_email) && trim($booking->contact_email) !== ''
            ? strtolower(trim($booking->contact_email))
            : null;

        $phone = is_string($booking->contact_phone) && trim($booking->contact_phone) !== ''
            ? trim($booking->contact_phone)
            : null;

        return ['email' => $email, 'phone' => $phone];
    }

    /**
     * TRAVEL-1009 (#6122) — templates i18n (config, locale du payload).
     *
     * @param  array<string, mixed>  $payload
     * @return array{title: string, body: string}
     */
    private function templateFor(string $eventType, array $payload, TravelBooking $booking, string $locale): array
    {
        $reference = (string) $booking->reference;
        $trackingUrl = $this->trackingUrl($reference);

        $templates = (array) config('travel.notifications.templates', []);
        $entry = isset($templates[$eventType]) && is_array($templates[$eventType]) ? $templates[$eventType] : [];
        $template = $entry[$locale] ?? $entry['fr'] ?? null;

        if (! is_array($template)) {
            $title = 'Mise à jour de votre réservation';
            $body = "Votre réservation {$reference} a été mise à jour : {$trackingUrl}";

            return ['title' => $title, 'body' => $body];
        }

        $body = str_replace(
            ['{reference}', '{tracking_url}'],
            [$reference, $trackingUrl],
            (string) ($template['body'] ?? ''),
        );

        // Motif d'annulation ajouté au corps (fr/en).
        if (in_array($eventType, ['travel.booking.cancelled.v1', 'travel.trip.cancelled.v1'], true)
            && ! empty($payload['reason'])) {
            $body .= $locale === 'en' ? ' Reason: '.(string) $payload['reason'].'.' : ' Motif : '.(string) $payload['reason'].'.';
        }

        return [
            'title' => (string) ($template['title'] ?? 'Mise à jour'),
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{email: string|null, phone: string|null}  $contact
     * @param  array{title: string, body: string}  $template
     */
    private function dispatchChannel(
        string $companyId,
        string $eventType,
        array $payload,
        TravelBooking $booking,
        array $contact,
        array $template,
        string $channel,
    ): void {
        $identifier = $channel === TravelNotificationConsent::CHANNEL_MAIL
            ? ($contact['email'] ?? null)
            : ($contact['phone'] ?? null);

        if ($identifier === null || $identifier === '') {
            $this->log($companyId, $payload, $eventType, 'n/a', $channel, 'skipped', "Pas d'identifiant pour le canal {$channel}.");

            return;
        }

        if (! $this->hasConsent($companyId, $identifier, $channel)) {
            $this->log($companyId, $payload, $eventType, $identifier, $channel, 'skipped', 'Consentement manquant pour le canal '.$channel.'.');

            return;
        }

        $title = $template['title'];
        $body = $template['body'];

        // WhatsApp : jamais de données financières ni de montants (spec §8.5).
        if ($channel === TravelNotificationConsent::CHANNEL_WHATSAPP && ! config('travel.notifications.whatsapp_allow_financial')) {
            $body = $this->stripFinancialData($body);
        }

        try {
            $status = match ($channel) {
                TravelNotificationConsent::CHANNEL_MAIL => $this->sendMail($identifier, $title, $body),
                TravelNotificationConsent::CHANNEL_SMS => $this->sendSms($identifier, $body),
                TravelNotificationConsent::CHANNEL_WHATSAPP => $this->sendWhatsApp($identifier, $body),
                default => 'skipped',
            };

            $this->log($companyId, $payload, $eventType, $identifier, $channel, $status);
        } catch (Throwable $e) {
            Log::channel('structured')->warning('travel.notification-send-failed', [
                'company_id' => $companyId,
                'event_type' => $eventType,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            $this->log($companyId, $payload, $eventType, $identifier, $channel, TravelNotificationLog::STATUS_FAILED, $e->getMessage());
        }
    }

    private function hasConsent(string $companyId, string $identifier, string $channel): bool
    {
        /** @var TravelNotificationConsent|null $consent */
        $consent = TravelNotificationConsent::query()
            ->where('company_id', $companyId)
            ->where('contact_identifier', $identifier)
            ->where('channel', $channel)
            ->first();

        return $consent instanceof TravelNotificationConsent && $consent->isActive();
    }

    private function sendMail(string $email, string $title, string $body): string
    {
        Mail::to($email)->send(new CommunicationMail($title, $body));

        return TravelNotificationLog::STATUS_SENT;
    }

    private function sendSms(string $phone, string $body): string
    {
        $accountSid = (string) config('services.twilio.account_sid', '');
        $authToken = (string) config('services.twilio.auth_token', '');
        $from = (string) config('services.twilio.from', '');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            return TravelNotificationLog::STATUS_SKIPPED;
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $from,
                'To' => $phone,
                'Body' => $body,
            ]);

        if (! $response->successful()) {
            Log::warning('Travel SMS dispatch failed', ['status' => $response->status()]);

            return TravelNotificationLog::STATUS_FAILED;
        }

        return TravelNotificationLog::STATUS_SENT;
    }

    private function sendWhatsApp(string $phone, string $body): string
    {
        $phoneNumberId = (string) config('travel.notifications.whatsapp_phone_number_id', '');
        $accessToken = (string) config('travel.notifications.whatsapp_access_token', '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return TravelNotificationLog::STATUS_SKIPPED;
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

        if (! $response->successful()) {
            Log::warning('Travel WhatsApp dispatch failed', ['status' => $response->status()]);

            return TravelNotificationLog::STATUS_FAILED;
        }

        return TravelNotificationLog::STATUS_SENT;
    }

    private function stripFinancialData(string $body): string
    {
        // Retire les montants/unités monétaires (ex. « 15 000 XAF », « 15,50 € »).
        return preg_replace('/\d[\d\s.,]*\s?(XAF|XOF|EUR|USD|FCFA|€|\$)/u', '[montant]', $body) ?? $body;
    }

    private function trackingUrl(string $reference): string
    {
        return rtrim((string) config('travel.notifications.tracking_base_url', ''), '/')
            .'/travel/bookings/'.$reference;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function log(
        string $companyId,
        array $payload,
        string $eventType,
        string $identifier,
        string $channel,
        string $status,
        ?string $reason = null,
    ): void {
        try {
            TravelNotificationLog::query()->create([
                'company_id' => $companyId,
                'event_id' => isset($payload['event_id']) ? (int) $payload['event_id'] : null,
                'event_type' => $eventType,
                'contact_identifier' => $identifier,
                'channel' => $channel,
                'status' => $status,
                'reason' => $reason,
                'payload_redacted' => $this->redact($payload),
            ]);
        } catch (Throwable $e) {
            Log::warning('travel.notification-log-failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        // Ne journalise jamais le n° de téléphone ou l'email en clair dans
        // le payload (déjà tracés dans `contact_identifier`).
        unset($payload['contact_email'], $payload['contact_phone'], $payload['document_number']);

        return $payload;
    }
}
