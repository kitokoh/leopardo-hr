<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxConsumerRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-414 (#6066) — Consommation de l'outbox TravelAgency.
 *
 * Miroir du pattern `crm:outbox-dispatch` (#5741) : claim atomique avec
 * lease, rejeu idempotent, retry avec backoff, dead-letter, pic de charge,
 * isolation cross-tenant.
 */
class TravelOutboxDispatchTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TravelOutboxPublisher $publisher;

    private TravelOutboxConsumerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(Schema::hasTable('travel_outbox_events'), 'la migration travel_outbox_events doit être exécutée');
        $this->createLedgerTable();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;

        $this->publisher = app(TravelOutboxPublisher::class);
        $this->registry = app(TravelOutboxConsumerRegistry::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('travel_test_effects');
        parent::tearDown();
    }

    public function test_event_is_dispatched_to_registered_consumer(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $this->publisher->publish(
            (string) $this->company->id,
            'travel.test.event',
            ['account_id' => 42],
        );

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(1, $this->effectCount(42), 'l\'effet est appliqué');
        self::assertSame(
            TravelOutboxEvent::STATUS_PUBLISHED,
            TravelOutboxEvent::query()->firstOrFail()->status,
        );
        self::assertNotNull(
            TravelOutboxEvent::query()->firstOrFail()->processed_at,
            'processed_at est horodaté',
        );
    }

    public function test_crash_between_publish_and_dispatch_loses_nothing(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        // « Crash » : l'événement est publié mais le dispatch ne tourne pas.
        $this->publisher->publish(
            (string) $this->company->id,
            'travel.test.event',
            ['account_id' => 99],
        );

        // « Redémarrage » : le dispatch reprend l'événement pending.
        Artisan::call('travel:outbox-dispatch');

        self::assertSame(1, $this->effectCount(99), 'zéro perte après crash');
        self::assertSame(0, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_PENDING)->count());
    }

    public function test_replay_after_crash_does_not_duplicate_effect(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'travel.test.event',
            ['account_id' => 55],
        );

        // 1er passage : effet appliqué.
        Artisan::call('travel:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55));

        // « Crash après effet » : rejeu manuel de l'événement.
        $event->forceFill(['status' => TravelOutboxEvent::STATUS_PENDING, 'available_at' => now()])->save();

        Artisan::call('travel:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55), 'zéro doublon après rejeu');
    }

    public function test_stale_processing_is_reclaimed_after_lease_expiry(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        // Worker « mort » : événement en processing avec lease expirée.
        $event = TravelOutboxEvent::factory()->create([
            'event_type' => 'travel.test.event',
            'status' => TravelOutboxEvent::STATUS_PROCESSING,
            'updated_at' => now()->subMinutes(30),
        ]);

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(TravelOutboxEvent::STATUS_PUBLISHED, $event->refresh()->status, 'reprise du processing orphelin');
        self::assertSame(1, $this->effectCount((int) ($event->payload_redacted['account_id'] ?? 0)));
    }

    public function test_event_within_lease_is_not_stolen_by_another_worker(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        TravelOutboxEvent::factory()->create([
            'event_type' => 'travel.test.event',
            'status' => TravelOutboxEvent::STATUS_PROCESSING,
            'updated_at' => now()->subMinutes(5), // lease encore active (15 min)
        ]);

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(
            TravelOutboxEvent::STATUS_PROCESSING,
            TravelOutboxEvent::query()->firstOrFail()->status,
            'un événement dans sa lease ne doit jamais être volé',
        );
        self::assertSame(0, $this->effectCount(0), 'aucun effet appliqué pendant la lease');
    }

    public function test_transient_errors_retry_with_backoff_and_succeed(): void
    {
        $consumer = new TravelFlakyConsumer('travel.test.flaky');
        $this->registry->register($consumer);

        $this->publisher->publish((string) $this->company->id, 'travel.test.flaky', ['account_id' => 1]);

        Artisan::call('travel:outbox-dispatch'); // 1re tentative : transitoire → retry
        $event = TravelOutboxEvent::query()->firstOrFail();
        self::assertSame(TravelOutboxEvent::STATUS_PENDING, $event->status);
        self::assertSame(1, $event->attempts);
        self::assertGreaterThan(now()->timestamp, (int) $event->available_at?->timestamp, 'backoff futur');

        // Backoff expiré → le retry réussit.
        $event->forceFill(['available_at' => now()->subMinute()])->save();
        Artisan::call('travel:outbox-dispatch');

        self::assertSame(TravelOutboxEvent::STATUS_PUBLISHED, $event->refresh()->status);
        self::assertSame(2, $event->attempts);
    }

    public function test_permanent_errors_go_to_dead_letter_immediately(): void
    {
        $consumer = new TravelPermanentFailConsumer('travel.test.permanent');
        $this->registry->register($consumer);

        $this->publisher->publish((string) $this->company->id, 'travel.test.permanent', ['account_id' => 1]);

        Artisan::call('travel:outbox-dispatch');

        $event = TravelOutboxEvent::query()->firstOrFail();
        self::assertSame(TravelOutboxEvent::STATUS_FAILED, $event->status, 'dead-letter immédiate');
        self::assertStringContainsString('permanent', (string) $event->last_error);
    }

    public function test_unregistered_event_type_goes_to_dead_letter(): void
    {
        $this->publisher->publish((string) $this->company->id, 'travel.unknown.event', ['account_id' => 1]);

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(
            TravelOutboxEvent::STATUS_FAILED,
            TravelOutboxEvent::query()->firstOrFail()->status,
            'consommateur absent → dead-letter (permanent)',
        );
    }

    public function test_load_pic_zero_loss_zero_duplicate(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $start = microtime(true);
        for ($i = 1; $i <= 200; $i++) {
            $this->publisher->publish((string) $this->company->id, 'travel.test.event', ['account_id' => $i]);
        }

        // Deux passes de 150 pour absorber le lot (limite par passe).
        Artisan::call('travel:outbox-dispatch', ['--limit' => 150]);
        Artisan::call('travel:outbox-dispatch', ['--limit' => 150]);

        $elapsed = microtime(true) - $start;

        self::assertSame(200, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_PUBLISHED)->count(), 'zéro perte');
        self::assertSame(0, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_FAILED)->count());
        self::assertSame(200, $consumer->appliedCount(), 'zéro doublon');
        self::assertLessThan(10.0, $elapsed, 'pic traité dans la fenêtre bornée');
    }

    public function test_events_are_consumed_within_the_tenant_context(): void
    {
        $consumer = new TenantAwareTravelConsumer('travel.test.tenant');
        $this->registry->register($consumer);

        $this->publisher->publish(
            (string) $this->company->id,
            'travel.test.tenant',
            ['account_id' => 7],
        );

        Artisan::call('travel:outbox-dispatch');

        self::assertTrue($consumer->ranWithinTenant, 'le consommateur s\'exécute dans le contexte tenant de l\'événement');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function effectCount(int $accountId): int
    {
        return (int) DB::table('travel_test_effects')->where('account_id', $accountId)->count();
    }

    private function createLedgerTable(): void
    {
        if (! Schema::hasTable('travel_test_effects')) {
            Schema::create('travel_test_effects', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('event_key', 255);
                $table->unique(['account_id', 'event_key']);
                $table->timestamps();
            });
        }
    }
}

/**
 * Consommateur de test : applique un effet enregistré dans un ledger avec
 * contrainte unique (account_id, event_key) — idempotent par construction.
 */
final class TravelLedgerConsumer implements TravelOutboxConsumer
{
    public int $applied = 0;

    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        $accountId = (int) ($payload['account_id'] ?? 0);
        $key = hash('sha256', $this->eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            DB::table('travel_test_effects')->insert([
                'account_id' => $accountId,
                'event_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->applied++;
        } catch (\Illuminate\Database\QueryException) {
            // Contrainte unique violée → rejeu : effet déjà appliqué, on ignore.
        }
    }
}

/**
 * Consommateur de test : échoue de façon transitoire (retry).
 */
final class TravelFlakyConsumer implements TravelOutboxConsumer
{
    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        throw new TransientOutboxException('provider indisponible (test)');
    }
}

/**
 * Consommateur de test : échec permanent (dead-letter immédiate).
 */
final class TravelPermanentFailConsumer implements TravelOutboxConsumer
{
    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        throw new PermanentOutboxException('permanent failure (test)');
    }
}

/**
 * Consommateur de test : vérifie que le contexte tenant est actif.
 */
final class TenantAwareTravelConsumer implements TravelOutboxConsumer
{
    public bool $ranWithinTenant = false;

    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        $this->ranWithinTenant = app(\App\Core\Tenant\TenantManager::class)->hasTenant();
    }
}
