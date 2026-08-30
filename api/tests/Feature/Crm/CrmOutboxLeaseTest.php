<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\CrmOutboxConsumer;
use App\Modules\CRM\Domain\Exceptions\PermanentOutboxException;
use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxConsumerRegistry;
use App\Modules\CRM\Infrastructure\Services\CrmOutboxPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC14 (INTEGRATION, #5890) — lease des événements outbox :
 * récupération après crash worker.
 *
 * Un événement `processing` dont le lease a expiré (worker mort) doit être
 * re-claimé par le prochain `crm:outbox-dispatch` ; un événement dans sa
 * lease ne doit JAMAIS être volé par un autre worker (zéro double
 * traitement).
 */
class CrmOutboxLeaseTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private CrmOutboxPublisher $publisher;

    private CrmOutboxConsumerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(Schema::hasTable('crm_outbox_events'), 'la migration crm_outbox_events doit être exécutée');
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

    public function test_stale_processing_is_reclaimed_after_lease_expiry(): void
    {
        $this->registry->register(new LeaseLedgerConsumer('crm.lease.event'));

        // « Crash » d'un worker entre le claim et l'effet : l'événement reste
        // bloqué en `processing` avec une lease expirée.
        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.lease.event',
            ['account_id' => 7],
        );
        $event->forceFill([
            'status' => CrmOutboxEvent::STATUS_PROCESSING,
            'updated_at' => now()->subMinutes(30),
        ])->save();

        // Le dispatch suivant re-claim l'orphelin et le traite.
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(
            CrmOutboxEvent::STATUS_SENT,
            $event->refresh()->status,
            'un événement processing au lease expiré doit être repris (crash worker)'
        );
        self::assertSame(1, $this->effectCount(7), 'l\'effet est appliqué une fois après reprise');
    }

    public function test_fresh_processing_is_not_reclaimed_within_lease(): void
    {
        $this->registry->register(new LeaseLedgerConsumer('crm.lease.event'));

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.lease.event',
            ['account_id' => 8],
        );
        // Lease encore valide : un AUTRE worker ne doit pas voler l'événement.
        $event->forceFill([
            'status' => CrmOutboxEvent::STATUS_PROCESSING,
            'updated_at' => now(),
        ])->save();

        Artisan::call('crm:outbox-dispatch');

        self::assertSame(
            CrmOutboxEvent::STATUS_PROCESSING,
            $event->refresh()->status,
            'un événement processing dans sa lease n\'est pas ré-claimé'
        );
        self::assertSame(0, $this->effectCount(8), 'aucun effet tant que le worker d\'origine n\'a pas terminé');
    }

    public function test_reclaim_preserves_attempts_budget(): void
    {
        $this->registry->register(new LeasePermanentFailConsumer('crm.lease.event'));

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.lease.event',
            ['account_id' => 9],
        );
        $event->forceFill([
            'status' => CrmOutboxEvent::STATUS_PROCESSING,
            'attempts' => CrmOutboxEvent::MAX_ATTEMPTS - 1,
            'updated_at' => now()->subMinutes(30),
        ])->save();

        // Le reclaim ne réinitialise PAS les tentatives : une boucle
        // crash-reclaim reste bornée par MAX_ATTEMPTS → dead-letter.
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(
            CrmOutboxEvent::STATUS_FAILED,
            $event->refresh()->status,
            'le budget de tentatives est préservé au reclaim (borné par MAX_ATTEMPTS)'
        );
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
 * Consommateur de test : effet idempotent (insertion isolée en savepoint —
 * piège 25P02, cf. CrmOutboxPublisher #5741).
 */
final class LeaseLedgerConsumer implements CrmOutboxConsumer
{
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
            DB::transaction(fn () => DB::table('crm_test_effects')->insert([
                'account_id' => $accountId,
                'event_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Effet déjà appliqué (rejeu) — idempotence.
        }
    }
}

/**
 * Consommateur de test : échoue en permanence (dead-letter).
 */
final class LeasePermanentFailConsumer implements CrmOutboxConsumer
{
    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        throw new PermanentOutboxException('payload invalide (simulation)');
    }
}
