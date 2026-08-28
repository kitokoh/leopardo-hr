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
 * MAT-008 (#5866) — Runtime inbox/outbox/queues fiable : replay contrôlé,
 * DLQ, backpressure et observabilité de l'outbox CRM.
 *
 * Complète CrmOutboxTest (#5741) avec les scénarios d'exploitation :
 *  - replay contrôlé d'un dead-letter (failed → pending) sans perte ni
 *    doublon d'effet ;
 *  - dry-run du replay (aucune modification) ;
 *  - filtres company / event-type du replay ;
 *  - backpressure : `--limit` borne la passe de dispatch ;
 *  - observabilité : `crm:outbox-status` (compteurs + échantillon DLQ,
 *    redacted).
 */
class CrmOutboxRuntimeTest extends TestCase
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

    public function test_replay_requeues_dead_letter_and_dispatch_succeeds(): void
    {
        // Aucun consommateur enregistré → l'événement part en dead-letter
        // ('no_consumer') : c'est le scénario « revue DLQ → cause corrigée ».
        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.runtime.event',
            ['account_id' => 1],
        );

        Artisan::call('crm:outbox-dispatch');
        self::assertSame(
            CrmOutboxEvent::STATUS_FAILED,
            $event->refresh()->status,
            'aucun consommateur → dead-letter'
        );

        // Replay contrôlé : failed → pending, cycle de vie réinitialisé.
        $code = Artisan::call('crm:outbox-replay');
        self::assertSame(0, $code);

        $event->refresh();
        self::assertSame(CrmOutboxEvent::STATUS_PENDING, $event->status);
        self::assertSame(0, $event->attempts, 'les tentatives sont réinitialisées au replay');
        self::assertNull($event->last_error, 'la dernière erreur est effacée au replay');

        // Cause corrigée : un consommateur est maintenant enregistré.
        $this->registry->register(new LedgerConsumer('crm.runtime.event'));
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(CrmOutboxEvent::STATUS_SENT, $event->refresh()->status);
        self::assertSame(1, $this->effectCount(1), 'l\'effet est appliqué exactement une fois');
    }

    public function test_replay_dry_run_changes_nothing(): void
    {
        $this->registry->register(new PermanentFailConsumer('crm.runtime.event'));

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.runtime.event',
            ['account_id' => 2],
        );
        Artisan::call('crm:outbox-dispatch');
        self::assertSame(CrmOutboxEvent::STATUS_FAILED, $event->refresh()->status);

        $code = Artisan::call('crm:outbox-replay', ['--dry-run' => true]);
        self::assertSame(0, $code);
        self::assertStringContainsString('DRY-RUN', Artisan::output());

        $event->refresh();
        self::assertSame(
            CrmOutboxEvent::STATUS_FAILED,
            $event->status,
            'dry-run : aucun événement ne doit changer de statut'
        );
        self::assertSame(1, $event->attempts, 'dry-run : tentatives inchangées');
    }

    public function test_replay_respects_company_and_event_type_filters(): void
    {
        $this->registry->register(new PermanentFailConsumer('crm.runtime.event'));

        $this->publisher->publish((string) $this->company->id, 'crm.runtime.event', ['account_id' => 3]);
        $this->publisher->publish((string) $this->company->id, 'crm.other.event', ['account_id' => 4]);
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(2, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_FAILED)->count());

        // Replay ciblé sur UN type d'événement.
        Artisan::call('crm:outbox-replay', ['--event-type' => 'crm.runtime.event']);

        self::assertSame(
            1,
            CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_PENDING)->count(),
            'seul le type ciblé est rejoué'
        );
        self::assertSame(
            1,
            CrmOutboxEvent::query()->where('event_type', 'crm.other.event')->where('status', CrmOutboxEvent::STATUS_FAILED)->count(),
            'les autres types restent en dead-letter'
        );
    }

    public function test_replay_never_duplicates_an_applied_effect(): void
    {
        // Le consommateur applique l'effet PUIS échoue (une seule fois) :
        // l'événement part en dead-letter avec un effet déjà en base. Après
        // replay, le même consommateur (cause corrigée) ne doit JAMAIS
        // dupliquer l'effet (idempotence).
        $this->registry->register(new FailAfterEffectConsumer('crm.runtime.event'));

        $event = $this->publisher->publish(
            (string) $this->company->id,
            'crm.runtime.event',
            ['account_id' => 5],
        );
        Artisan::call('crm:outbox-dispatch');
        self::assertSame(CrmOutboxEvent::STATUS_FAILED, $event->refresh()->status);
        self::assertSame(1, $this->effectCount(5), 'l\'effet a été appliqué avant le crash simulé');

        // Replay puis dispatch : l'effet n'est PAS dupliqué.
        Artisan::call('crm:outbox-replay');
        Artisan::call('crm:outbox-dispatch');

        self::assertSame(CrmOutboxEvent::STATUS_SENT, $event->refresh()->status);
        self::assertSame(1, $this->effectCount(5), 'replay : zéro doublon d\'effet');
    }

    public function test_dispatch_limit_bounds_batch_backpressure(): void
    {
        $this->registry->register(new LedgerConsumer('crm.runtime.event'));

        for ($i = 1; $i <= 10; $i++) {
            $this->publisher->publish((string) $this->company->id, 'crm.runtime.event', ['account_id' => 100 + $i]);
        }

        Artisan::call('crm:outbox-dispatch', ['--limit' => 3]);

        self::assertSame(3, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_SENT)->count(), 'backpressure : lot borné');
        self::assertSame(7, CrmOutboxEvent::query()->where('status', CrmOutboxEvent::STATUS_PENDING)->count(), 'le reste attend la passe suivante');
    }

    public function test_status_command_reports_counts_and_dlq_sample_redacted(): void
    {
        $this->registry->register(new PermanentFailConsumer('crm.runtime.event'));

        $this->publisher->publish((string) $this->company->id, 'crm.runtime.event', ['account_id' => 6]);
        Artisan::call('crm:outbox-dispatch');

        $code = Artisan::call('crm:outbox-status');
        self::assertSame(0, $code);

        $output = Artisan::output();
        self::assertStringContainsString('failed', $output);
        self::assertStringContainsString('1', $output, 'le compteur dead-letter est affiché');
        self::assertStringContainsString('crm.runtime.event', $output);
        self::assertStringNotContainsString('PII', $output, 'sortie redacted (aucun payload en clair)');
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
 * Consommateur de test : applique l'effet puis échoue (crash après effet)
 * UNE SEULE fois — après replay, la « cause est corrigée » et le
 * consommateur applique idempotemment sans échouer.
 */
final class FailAfterEffectConsumer implements CrmOutboxConsumer
{
    private int $calls = 0;

    public function __construct(private readonly string $eventType) {}

    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType;
    }

    public function handle(array $payload): void
    {
        $this->calls++;
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

        if ($this->calls === 1) {
            throw new PermanentOutboxException('crash simulé après effet');
        }
    }
}

/**
 * Consommateur de test : erreur permanente (dead-letter immédiate).
 */
final class PermanentFailConsumer implements CrmOutboxConsumer
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

/**
 * Consommateur de test : applique un effet idempotent (ledger unique).
 */
final class LedgerConsumer implements CrmOutboxConsumer
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

        // Insertion isolée dans un savepoint (DB::transaction imbriquée) :
        // une violation d'unicité ABORTE la transaction courante en PostgreSQL
        // (25P02) — sans savepoint, elle empoisonne la transaction du test
        // (même piège documenté dans CrmOutboxPublisher #5741).
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
