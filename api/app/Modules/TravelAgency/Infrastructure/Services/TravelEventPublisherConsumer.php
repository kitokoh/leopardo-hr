<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Events\TravelEventPublished;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentTravelOutboxException;
use Illuminate\Support\Facades\Log;

/**
 * #6066 (TRAVEL-414) — Consommateur de publication des événements
 * TravelAgency.
 *
 * Pour chaque événement `travel.*.v1` de l'outbox, publie l'événement sur le
 * bus tenant-scopé de la plateforme (`TenantEventDispatcher`, Core — aucun
 * import inter-modules, règle #5584) et trace la publication en log
 * structuré. Les BC consommateurs (BC-13 COMMS, Accounting, CRM, Documents —
 * spec §8.4/§8.5) s'abonnent côté plateforme sans import inter-modules ; un
 * rejeu republie le même événement sans effet dupliqué (publication =
 * signal ; les effets restent idempotents côté abonnés).
 */
final class TravelEventPublisherConsumer implements TravelOutboxConsumer
{
    /** @var list<string> */
    private const SUPPORTED_EVENTS = [
        'travel.trip.published.v1',
        'travel.trip.cancelled.v1',
        'travel.booking.pending.v1',
        'travel.booking.confirmed.v1',
        'travel.booking.cancelled.v1',
        'travel.payment.confirmed.v1',
        'travel.payment.refunded.v1',
        'travel.ticket.issued.v1',
        'travel.ticket.checked_in.v1',
    ];

    public function __construct(
        private readonly TenantEventDispatcher $dispatcher,
        private readonly TenantManager $tenants,
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
        $company = $this->tenants->current();

        if (! $company instanceof Company) {
            throw new PermanentTravelOutboxException(
                'Aucun contexte tenant résolu — publication refusée (fail-closed).'
            );
        }

        $this->dispatcher->dispatch(
            new TravelEventPublished((string) $company->id, $eventType, $payload)
        );

        Log::channel('structured')->info('travel.outbox.published', [
            'company_id' => $company->id,
            'event_type' => $eventType,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }
}
