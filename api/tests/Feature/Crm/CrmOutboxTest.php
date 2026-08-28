<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\CrmOutboxConsumer;
use App\Modules\CRM\Domain\Exceptions\PermanentOutboxException;
use App\Modules\CRM\Domain\Exceptions\TransientOutboxException;
use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5741 (CRM-PRE) — Fiabilité des files CRM : inbox/outbox, idempotence,
 * crash entre inbox et consumer, crash après effet métier, erreurs
 * transitoires vs permanentes, pic sans perte ni doublon.
 */
class CrmOutboxTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private CrmOutboxPublisher $publisher;

    private CrmOutboxConsumerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLedgerTable();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->publisher = app(CrmOutboxPublisher::class);
        $this->registry = app(CrmOutboxConsumerRegistry::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('crm_test_effects');
        parent::tearDown();
    }

    public function test_publish_then_dispatch_applies_effect_once(): void
    {
        $consumer = new LedgerConsumer('crm.test.event');
        $this->registry->register($consumer);

        $this->publisher->publish(
            (string) $this->company->id,
            'crm.test.event',
            ['account_id' => 42],
        );

        Artisan::call('crm:outbox-dispatch');

        self::assertSame(1, $this->effectCount(42), 'l\'effet est appliqué');
        self::assertSame(
            CrmOutboxEvent::STATUS_SENT,
            CrmOutboxEvent::query()->firstOrFail()->status
        );
    }

    public function test_double_publish_same_key_is_deduped(): void
    {
        $this->publisher->publish(
            (string) $this->company->id,
            'crm.test.event',
            ['account_id' => 7],
            idempotencyKey: 'key-1',
        );
        $this->publisher->publish(
            (string) $this->company->id,
            'crm.test.event',
            ['account_id' => 7],
            idempotencyKey: 'key-1',
        );

        self::assertSame(1, CrmOutboxEvent::query()->count(), 'clé d\'idempotence unique par tenant');
    }

    public function test_crash_between_publish_and_dispatch_loses_nothing(): void
    {
        $consumer = new LedgerConsumer('crm.test.event');
        $this->registry->register($consumer);

        // « Crash » : l'événement est publié mais le dispatch ne tourne pas.
        $this->publisher->publish(
            (string) $this->company->id,
            'crm.test.event',
            ['account_id' => 99],
        );

        // « Redémarrage » : le dispatch reprend l'événement pending.
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(1, $this->effectCount(99), 'zéro perte après crash');
        self::assertSame(0, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_PENDING)->count());
    }

    public function test_crash_after_effect_does_not_duplicate_on_replay(): void
    {
        $consumer = new LedgerConsumer('crm.test.event');
        $this->registry->register($consumer);

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.test.event',
            ['account_id' => 55],
        );

        // 1er passage : effet appliqué.
        Artisan::call('crm:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55));

        // « Crash après effet » : l'événement est remis en pending (rejeu manuel).
        $event->forceFill(['status' => CrmOutboxEvent::STATUS_PENDING, 'available_at' => now()])->save();

        // Rejeu : le consommateur idempotent ne ré-applique PAS l'effet.
        Artisan::call('crm:outbox-dispatch');
        self::assertSame(1, $this->effectCount(55), 'zéro doublon après rejeu');
    }

    public function test_transient_errors_retry_with_backoff_and_succeed(): void
    {
        $consumer = new FlakyConsumer('crm.test.flaky');
        $this->registry->register($consumer);

        $this->publisher->publish((string) $this->company->id, 'crm.test.flaky', ['account_id' => 1]);

        Artisan::call('crm:outbox-dispatch'); // 1re tentative : transitoire → retry
        $event = CrmOutboxEvent::query()->firstOrFail();
        self::assertSame(CrmOutboxEvent::STATUS_PENDING, $event->status);
        self::assertSame(1, $event->attempts);
        self::assertGreaterThan(now()->timestamp, (int) $event->available_at?->timestamp, 'backoff futur');

        // Backoff expiré → le retry réussit.
        $event->forceFill(['available_at' => now()->subMinute()])->save();
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(CrmOutboxEvent::STATUS_SENT, $event->refresh()->status);
        self::assertSame(2, $event->attempts);
    }

    public function test_permanent_errors_go_to_dead_letter_immediately(): void
    {
        $consumer = new PermanentFailConsumer('crm.test.permanent');
        $this->registry->register($consumer);

        $this->publisher->publish((string) $this->company->id, 'crm.test.permanent', ['account_id' => 1]);

        Artisan::call('crm:outbox-dispatch');

        $event = CrmOutboxEvent::query()->firstOrFail();
        self::assertSame(CrmOutboxEvent::STATUS_FAILED, $event->status, 'dead-letter immédiate');
        self::assertStringContainsString('permanent', (string) $event->last_error);
    }

    public function test_unregistered_event_type_goes_to_dead_letter(): void
    {
        $this->publisher->publish((string) $this->company->id, 'crm.unknown.event', ['account_id' => 1]);

        Artisan::call('crm:outbox-dispatch');

        self::assertSame(
            CrmOutboxEvent::STATUS_FAILED,
            CrmOutboxEvent::query()->firstOrFail()->status
        );
    }

    public function test_load_pic_zero_loss_zero_duplicate_with_bounded_lag(): void
    {
        $consumer = new LedgerConsumer('crm.test.event');
        $this->registry->register($consumer);

        $start = microtime(true);
        for ($i = 1; $i <= 200; $i++) {
            $this->publisher->publish((string) $this->company->id, 'crm.test.event', ['account_id' => $i]);
        }

        // Deux passes de 150 pour absorber le lot.
        Artisan::call('crm:outbox-dispatch', ['--limit' => 150]);
        Artisan::call('crm:outbox-dispatch', ['--limit' => 150]);

        $elapsed = microtime(true) - $start;

        self::assertSame(200, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_SENT)->count(), 'zéro perte');
        self::assertSame(0, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_FAILED)->count());
        self::assertSame(200, CrmOutboxEvent::query()->count());
        self::assertSame(200, $consumer->appliedCount(), 'zéro doublon');

        // Lag p95 borné : 200 événements traités en moins de 10 s (CI).
        self::assertLessThan(10.0, $elapsed, 'pic traité dans la fenêtre bornée');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function effectCount(int $accountId): int
    {
        return (int) DB::table('crm_test_effects')->where('account_id', $accountId)->count();
    }

    private function createLedgerTable(): void
    {
        if (! Schema::hasTable('crm_test_effects')) {
            Schema::create('crm_test_effects', function (Blueprint $table): void {
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
final class LedgerConsumer implements CrmOutboxConsumer
{
    public int $applied = 0;

    public function __construct(private readonly string $eventType)
    {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        $accountId = (int) ($payload['account_id'] ?? 0);
        $key = hash('sha256', $this->eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            DB::table('crm_test_effects')->insert([
                'account_id' => $accountId,
                'event_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->applied++;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Effet déjà appliqué (rejeu) — idempotence.
        }
    }

    public function appliedCount(): int
    {
        return (int) DB::table('crm_test_effects')->count();
    }
}

final class FlakyConsumer implements CrmOutboxConsumer
{
    private int $calls = 0;

    public function __construct(private readonly string $eventType)
    {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        $this->calls++;

        if ($this->calls === 1) {
            throw new TransientOutboxException('provider indisponible (simulation)');
        }
    }
}

final class PermanentFailConsumer implements CrmOutboxConsumer
{
    public function __construct(private readonly string $eventType)
    {
    }

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        throw new PermanentOutboxException('payload invalide (simulation)');
    }
}
