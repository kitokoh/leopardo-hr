<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentTravelOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientTravelOutboxException;
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
 * Pipeline miroir du pattern crm_outbox (#5741) : dédup (rejeu), crash/
 * replay sans perte ni doublon, retry/backoff transitoire, dead-letter
 * permanente, événement inconnu, pic 200 événements, refus structurel
 * cross-tenant (contexte du tenant de l'événement imposé au consommateur).
 */
class TravelOutboxDispatchTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TravelOutboxPublisher $publisher;

    private TravelOutboxConsumerRegistry $registry;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(Schema::hasTable('travel_outbox_events'), 'la migration travel_outbox_events doit être exécutée');
        $this->createLedgerTable();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->publisher = app(TravelOutboxPublisher::class);
        $this->registry = app(TravelOutboxConsumerRegistry::class);
        $this->tenants = app(TenantManager::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('travel_test_effects');
        parent::tearDown();
    }

    public function test_publish_then_dispatch_applies_effect_once(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $this->publisher->publish(
            (string) $this->companyA->id,
            'travel.test.event',
            ['account_id' => 42],
        );

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(1, $this->effectCount(42), 'l\'effet est appliqué');
        self::assertSame(
            TravelOutboxEvent::STATUS_PUBLISHED,
            TravelOutboxEvent::query()->firstOrFail()->status,
            'l\'événement est publié après consommation'
        );
        self::assertNotNull(TravelOutboxEvent::query()->firstOrFail()->processed_at, 'processed_at horodaté');
    }

    public function test_double_publish_same_key_is_deduped(): void
    {
        $this->publisher->publish(
            (string) $this->companyA->id,
            'travel.test.event',
            ['account_id' => 7],
            idempotencyKey: 'key-1',
        );
        $this->publisher->publish(
            (string) $this->companyA->id,
            'travel.test.event',
            ['account_id' => 7],
            idempotencyKey: 'key-1',
        );

        self::assertSame(1, TravelOutboxEvent::query()->count(), 'clé d\'idempotence unique par tenant');
    }

    public function test_crash_between_publish_and_dispatch_loses_nothing(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        // « Crash » : l'événement est publié mais le dispatch ne tourne pas.
        $this->publisher->publish((string) $this->companyA->id, 'travel.test.event', ['account_id' => 99]);

        // « Redémarrage » : le dispatch reprend l'événement pending.
        Artisan::call('travel:outbox-dispatch');

        self::assertSame(1, $this->effectCount(99), 'zéro perte après crash');
        self::assertSame(0, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_PENDING)->count());
    }

    public function test_crash_after_effect_does_not_duplicate_on_replay(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $event = $this->publisher->publish(
            (string) $this->companyA->id,
            'travel.test.event',
            ['account_id' => 55],
        );

        // 1er passage : effet appliqué.
        Artisan::call('travel:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55));

        // « Crash après effet » : l'événement est remis en pending (rejeu manuel).
        $event->forceFill(['status' => TravelOutboxEvent::STATUS_PENDING, 'available_at' => now()])->save();

        // Rejeu : le consommateur idempotent ne ré-applique PAS l'effet.
        Artisan::call('travel:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55), 'zéro doublon après rejeu');
    }

    public function test_transient_errors_retry_with_backoff_and_succeed(): void
    {
        $consumer = new TravelFlakyConsumer('travel.test.flaky');
        $this->registry->register($consumer);

        $this->publisher->publish((string) $this->companyA->id, 'travel.test.flaky', ['account_id' => 1]);

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

        $this->publisher->publish((string) $this->companyA->id, 'travel.test.permanent', ['account_id' => 1]);

        Artisan::call('travel:outbox-dispatch');

        $event = TravelOutboxEvent::query()->firstOrFail();
        self::assertSame(TravelOutboxEvent::STATUS_FAILED, $event->status, 'dead-letter immédiate');
        self::assertStringContainsString('permanent', (string) $event->last_error);
    }

    public function test_unregistered_event_type_goes_to_dead_letter(): void
    {
        $this->publisher->publish((string) $this->companyA->id, 'travel.unknown.event', ['account_id' => 1]);

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(
            TravelOutboxEvent::STATUS_FAILED,
            TravelOutboxEvent::query()->firstOrFail()->status
        );
    }

    public function test_consumer_never_sees_another_tenant(): void
    {
        // Consommateur qui refuse si le tenant courant n'est pas celui de
        // l'événement (garde cross-tenant structurelle du dispatch).
        $consumer = new TravelTenantAwareConsumer((string) $this->companyA->id);
        $this->registry->register($consumer);

        // Événement du tenant A : consommé DANS le contexte A (accepté).
        $this->publisher->publish((string) $this->companyA->id, 'travel.test.tenant', ['account_id' => 1]);
        // Événement du tenant B : consommé DANS le contexte B (le
        // consommateur le refuse → dead-letter, jamais d'effet cross-tenant).
        $this->publisher->publish((string) $this->companyB->id, 'travel.test.tenant', ['account_id' => 2]);

        Artisan::call('travel:outbox-dispatch');

        self::assertSame(
            TravelOutboxEvent::STATUS_PUBLISHED,
            TravelOutboxEvent::query()->where('company_id', $this->companyA->id)->firstOrFail()->status,
            'événement A publié dans son tenant'
        );
        self::assertSame(
            TravelOutboxEvent::STATUS_FAILED,
            TravelOutboxEvent::query()->where('company_id', $this->companyB->id)->firstOrFail()->status,
            'événement B jamais appliqué hors de son tenant'
        );
        self::assertSame(1, $this->effectCount(1), 'un seul effet, dans le bon tenant');
    }

    public function test_load_pic_zero_loss_zero_duplicate_with_bounded_lag(): void
    {
        $consumer = new TravelLedgerConsumer('travel.test.event');
        $this->registry->register($consumer);

        $start = microtime(true);
        for ($i = 1; $i <= 200; $i++) {
            $this->publisher->publish((string) $this->companyA->id, 'travel.test.event', ['account_id' => $i]);
        }

        // Deux passes de 150 pour absorber le lot.
        Artisan::call('travel:outbox-dispatch', ['--limit' => 150]);
        Artisan::call('travel:outbox-dispatch', ['--limit' => 150]);

        $elapsed = microtime(true) - $start;

        self::assertSame(200, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_PUBLISHED)->count(), 'zéro perte');
        self::assertSame(0, TravelOutboxEvent::query()->where('status', TravelOutboxEvent::STATUS_FAILED)->count());
        self::assertSame(200, TravelOutboxEvent::query()->count());
        self::assertSame(200, $consumer->appliedCount(), 'zéro doublon');

        // Lag p95 borné : 200 événements traités en moins de 10 s (CI).
        self::assertLessThan(10.0, $elapsed, 'pic traité dans la fenêtre bornée');
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
 * contrainte unique (account_id, event_key) — idempotent par construction :
 * un rejeu viole la contrainte → effet NON dupliqué.
 */
final class TravelLedgerConsumer implements TravelOutboxConsumer
{
    public int $applied = 0;

    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(string $eventType, array $payload): void
    {
        $accountId = (int) ($payload['account_id'] ?? 0);
        $key = hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            DB::table('travel_test_effects')->insert([
                'account_id' => $accountId,
                'event_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->applied++;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Rejeu : déjà appliqué → aucun doublon.
        }
    }

    public function appliedCount(): int
    {
        return $this->applied;
    }
}

/**
 * Consommateur de test flaky : échoue une fois en transitoire puis réussit.
 */
final class TravelFlakyConsumer implements TravelOutboxConsumer
{
    private bool $failed = false;

    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(string $eventType, array $payload): void
    {
        if (! $this->failed) {
            $this->failed = true;

            throw new TransientTravelOutboxException('temporarily unavailable');
        }

        DB::table('travel_test_effects')->insert([
            'account_id' => (int) ($payload['account_id'] ?? 0),
            'event_key' => hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Consommateur de test en échec permanent : dead-letter immédiate.
 */
final class TravelPermanentFailConsumer implements TravelOutboxConsumer
{
    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(string $eventType, array $payload): void
    {
        throw new PermanentTravelOutboxException('permanent failure');
    }
}

/**
 * Consommateur de test tenant-aware : refuse tout événement dont le tenant
 * courant n'est pas celui attendu (garde cross-tenant).
 */
final class TravelTenantAwareConsumer implements TravelOutboxConsumer
{
    public function __construct(private readonly string $expectedCompanyId) {}

    public function supports(string $eventType): bool
    {
        return $eventType === 'travel.test.tenant';
    }

    public function handle(string $eventType, array $payload): void
    {
        /** @var Company|null $company */
        $company = app(TenantManager::class)->current();

        if (! $company instanceof Company || (string) $company->id !== $this->expectedCompanyId) {
            throw new PermanentTravelOutboxException('cross-tenant consumer refused');
        }

        DB::table('travel_test_effects')->insert([
            'account_id' => (int) ($payload['account_id'] ?? 0),
            'event_key' => hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
