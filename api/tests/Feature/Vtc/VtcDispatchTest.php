<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Application\Services\VtcDispatchService;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideAccepted;
use App\Modules\Vtc\Domain\Events\VtcRideExpired;
use App\Modules\Vtc\Domain\Events\VtcRideRequested;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Exceptions\VtcOfferNotCurrentException;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Infrastructure\Jobs\ExpireVtcOfferJob;
use App\Modules\Vtc\Infrastructure\Jobs\OfferRideToNearestDriverJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * VTC-04 (#8360, BC-34 VTC) — moteur de dispatch : matching du chauffeur
 * disponible le plus proche (via le core geo BC-33), cascade d'offres
 * séquentielles (timeout 30 s), acceptation sous verrou, expiration.
 *
 * Le driver de file est `sync` en test : Queue::fake() capture les jobs
 * différés (ExpireVtcOfferJob) — les timeouts sont ensuite simulés en
 * appelant directement le service.
 */
class VtcDispatchTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    public function test_ride_requested_event_enqueues_dispatch_job(): void
    {
        Queue::fake();

        event(new VtcRideRequested('company-uuid', 42, 'VTC-2026-000042', null));

        Queue::assertPushed(OfferRideToNearestDriverJob::class, static fn (OfferRideToNearestDriverJob $job): bool => $job->rideId === 42);
    }

    public function test_dispatch_offers_nearest_available_driver_first(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();

            $near = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create(['name' => 'Proche']);
            VtcDriver::factory()->availableAt(4.0611, 9.7779)->create(['name' => 'Loin']);
            VtcDriver::factory()->availableAt(4.1511, 9.8679)->create(['name' => 'Hors rayon']);
            VtcDriver::factory()->create(['name' => 'Offline', 'status' => VtcDriverStatus::Offline->value]);
            VtcDriver::factory()->create([
                'name' => 'Busy tres proche',
                'status' => VtcDriverStatus::Busy->value,
                'current_latitude' => 4.0512,
                'current_longitude' => 9.7680,
                'location_updated_at' => now(),
            ]);

            $ride = $this->createDispatchingRide();

            $this->dispatch()->offerToNextCandidate($ride);

            // Le plus proche DISPONIBLE est sollicité en premier (busy et
            // offline exclus structurellement, hors rayon écarté par le geo).
            $offer = $ride->events()->where('type', 'dispatch.offer_sent')->sole();
            self::assertSame($near->id, $offer->payload['driver_id']);
            self::assertSame(1, $offer->payload['seq']);
            self::assertIsInt($offer->payload['distance_meters']);

            // L'offre courante est posée en metadata (source de vérité).
            $pending = $ride->refresh()->metadata['pending_offer'] ?? null;
            self::assertSame($near->id, $pending['driver_id'] ?? null);
            self::assertSame(1, $pending['seq'] ?? null);

            Queue::assertPushed(ExpireVtcOfferJob::class, static fn (ExpireVtcOfferJob $job): bool => $job->driverId === $near->id && $job->offerSeq === 1);
        });
    }

    public function test_timeout_cascades_to_the_next_candidate(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();

            $near = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create(['name' => 'Proche']);
            $far = VtcDriver::factory()->availableAt(4.0611, 9.7779)->create(['name' => 'Loin']);

            $ride = $this->createDispatchingRide();
            $dispatch = $this->dispatch();

            $dispatch->offerToNextCandidate($ride);

            // Timeout de la première offre → cascade sur le second.
            $dispatch->expireOffer((string) $company->id, $ride->id, $near->id, 1);

            $types = $ride->events()->orderBy('id')->pluck('type')->all();
            self::assertSame(['dispatch.offer_sent', 'dispatch.offer_expired', 'dispatch.offer_sent'], $types);

            $pending = $ride->refresh()->metadata['pending_offer'] ?? null;
            self::assertSame($far->id, $pending['driver_id'] ?? null);
            self::assertSame(2, $pending['seq'] ?? null);
        });
    }

    public function test_stale_timeout_job_is_a_no_op(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();

            $near = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create();
            $far = VtcDriver::factory()->availableAt(4.0611, 9.7779)->create();

            $ride = $this->createDispatchingRide();
            $dispatch = $this->dispatch();

            $dispatch->offerToNextCandidate($ride);
            $dispatch->expireOffer((string) $company->id, $ride->id, $near->id, 1);

            // Rejeu d'un job de timeout déjà consommé : aucun effet.
            $dispatch->expireOffer((string) $company->id, $ride->id, $near->id, 1);

            self::assertSame(3, $ride->events()->count());

            // Timeout avec une séquence inconnue : aucun effet non plus.
            $dispatch->expireOffer((string) $company->id, $ride->id, $far->id, 99);

            self::assertSame(3, $ride->events()->count());
        });
    }

    public function test_decline_moves_immediately_to_next_candidate(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();

            $near = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create();
            $far = VtcDriver::factory()->availableAt(4.0611, 9.7779)->create();

            $ride = $this->createDispatchingRide();
            $dispatch = $this->dispatch();

            $dispatch->offerToNextCandidate($ride);
            $dispatch->declineOffer($ride->refresh(), $near->id);

            $types = $ride->events()->orderBy('id')->pluck('type')->all();
            self::assertSame(['dispatch.offer_sent', 'dispatch.offer_declined', 'dispatch.offer_sent'], $types);

            $pending = $ride->refresh()->metadata['pending_offer'] ?? null;
            self::assertSame($far->id, $pending['driver_id'] ?? null);

            // Déclinaison d'une offre qui n'est pas la courante : 409 métier.
            $this->expectException(VtcOfferNotCurrentException::class);
            $dispatch->declineOffer($ride->refresh(), $near->id);
        });
    }

    public function test_acceptance_locks_the_ride_and_sets_driver_busy(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();
            Event::fake([VtcRideAccepted::class]);

            $near = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create();
            $far = VtcDriver::factory()->availableAt(4.0611, 9.7779)->create();

            $ride = $this->createDispatchingRide();
            $dispatch = $this->dispatch();

            $dispatch->offerToNextCandidate($ride);

            // Un chauffeur jamais sollicité ne peut pas accepter.
            try {
                $dispatch->acceptOffer($ride->refresh(), $far->id);
                self::fail('VtcOfferNotCurrentException attendue.');
            } catch (VtcOfferNotCurrentException) {
                self::assertTrue(true);
            }

            $accepted = $dispatch->acceptOffer($ride->refresh(), $near->id);

            self::assertSame(VtcRideStatus::Accepted, $accepted->status);
            self::assertSame($near->id, $accepted->driver_id);
            self::assertNotNull($accepted->accepted_at);
            self::assertArrayNotHasKey('pending_offer', $accepted->metadata ?? []);

            // Le chauffeur passe busy : hors matching.
            self::assertSame(VtcDriverStatus::Busy, VtcDriver::query()->findOrFail($near->id)->status);

            // Double acceptation impossible (verrou + état terminal dispatché).
            $this->expectException(InvalidRideTransitionException::class);
            try {
                $dispatch->acceptOffer($ride->refresh(), $far->id);
            } finally {
                Event::assertDispatched(VtcRideAccepted::class, static fn (VtcRideAccepted $event): bool => $event->rideId === $ride->id && $event->driverId === $near->id);
            }
        });
    }

    public function test_ride_expires_when_no_driver_is_available(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();
            Event::fake([VtcRideExpired::class]);

            VtcDriver::factory()->availableAt(4.1511, 9.8679)->create(['name' => 'Hors rayon']);

            $ride = $this->createDispatchingRide();

            $this->dispatch()->offerToNextCandidate($ride);

            $expired = $ride->refresh();
            self::assertSame(VtcRideStatus::Expired, $expired->status);
            self::assertNotNull($expired->expired_at);

            $exhausted = $ride->events()->where('type', 'dispatch.exhausted')->sole();
            self::assertSame('no_available_driver_in_radius', $exhausted->payload['reason']);

            Event::assertDispatched(VtcRideExpired::class, static fn (VtcRideExpired $event): bool => $event->rideId === $ride->id);
        });
    }

    public function test_ride_expires_after_max_offers(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();
            config()->set('vtc.dispatch.max_offers', 2);

            $first = VtcDriver::factory()->availableAt(4.0515, 9.7682)->create();
            $second = VtcDriver::factory()->availableAt(4.0611, 9.7779)->create();
            // Un troisième candidat ne sera jamais sollicité (cascade bornée).
            VtcDriver::factory()->availableAt(4.0550, 9.7700)->create();

            $ride = $this->createDispatchingRide();
            $dispatch = $this->dispatch();

            $dispatch->offerToNextCandidate($ride);
            $dispatch->expireOffer((string) $company->id, $ride->id, $first->id, 1);
            $dispatch->expireOffer((string) $company->id, $ride->id, $second->id, 2);

            $expired = $ride->refresh();
            self::assertSame(VtcRideStatus::Expired, $expired->status);

            $exhausted = $ride->events()->where('type', 'dispatch.exhausted')->sole();
            self::assertSame('max_offers_reached', $exhausted->payload['reason']);
            self::assertSame(2, $exhausted->payload['offers_sent']);
        });
    }

    public function test_dispatch_never_offers_drivers_of_another_tenant(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();

        $this->withTenantContext($companyB, function (): void {
            VtcDriver::factory()->availableAt(4.0512, 9.7680)->create(['name' => 'Chauffeur B tres proche']);
        });

        $this->withTenantContext($companyA, function (): void {
            Queue::fake();
            Event::fake([VtcRideExpired::class]);

            $ride = $this->createDispatchingRide();

            $this->dispatch()->offerToNextCandidate($ride);

            // Aucun chauffeur du tenant A : expiration immédiate, le
            // chauffeur de B n'a JAMAIS été sollicité.
            self::assertSame(VtcRideStatus::Expired, $ride->refresh()->status);
            self::assertSame(0, $ride->events()->where('type', 'dispatch.offer_sent')->count());
        });
    }

    public function test_offer_is_never_sent_on_a_non_dispatching_ride(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function (): void {
            Queue::fake();

            VtcDriver::factory()->availableAt(4.0515, 9.7682)->create();

            $ride = VtcRide::factory()->create(['status' => VtcRideStatus::Cancelled->value]);

            $this->dispatch()->offerToNextCandidate($ride);

            self::assertSame(0, $ride->events()->count());
            self::assertSame(VtcRideStatus::Cancelled, $ride->refresh()->status);
        });
    }

    private function dispatch(): VtcDispatchService
    {
        /** @var VtcDispatchService $service */
        $service = $this->app->make(VtcDispatchService::class);

        return $service;
    }

    private function createDispatchingRide(): VtcRide
    {
        /** @var VtcRide $ride */
        $ride = VtcRide::factory()->create([
            'status' => VtcRideStatus::Dispatching->value,
            'pickup_latitude' => 4.0511,
            'pickup_longitude' => 9.7679,
        ]);

        return $ride;
    }

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        return $company;
    }
}
