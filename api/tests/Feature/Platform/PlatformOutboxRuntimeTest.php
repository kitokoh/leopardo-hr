<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Platform\Domain\Contracts\PlatformOutboxConsumer;
use App\Modules\Platform\Domain\Exceptions\PermanentOutboxException;
use App\Modules\Platform\Domain\Exceptions\TransientOutboxException;
use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use App\Modules\Platform\Infrastructure\Services\Consumers\PlatformCompanyCreatedAuditConsumer;
use App\Modules\Platform\Infrastructure\Services\Consumers\PlatformSubscriptionPaidAuditConsumer;
use App\Modules\Platform\Infrastructure\Services\PlatformOutboxConsumerRegistry;
use App\Modules\Platform\Infrastructure\Services\PlatformOutboxPublisher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-008 (#5866) — Runtime inbox/outbox/queues fiable (BC-01 PLATFORM).
 *
 * Critères d'acceptation :
 *  - un pic simulé ne perd ni ne duplique un message ;
 *  - le replay contrôlé et la dead-letter sont testés ;
 *  - backpressure (limit), retries avec backoff, lease après crash worker.
 */
class PlatformOutboxRuntimeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private PlatformOutboxPublisher $publisher;

    private PlatformOutboxConsumerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(Schema::hasTable('platform_outbox_events'), 'la migration platform_outbox_events doit être exécutée');
        $this->createEffectsTable();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        $this->publisher = app(PlatformOutboxPublisher::class);
        $this->registry = app(PlatformOutboxConsumerRegistry::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('platform_test_effects');
        parent::tearDown();
    }

    public function test_company_created_event_flows_through_outbox_to_audit(): void
    {
        $this->publisher->publish(
            companyId: $this->company->id,
            eventType: PlatformCompanyCreatedAuditConsumer::EVENT_TYPE,
            payload: [
                'event_id' => 'company.created.'.$this->company->id,
                'company_id' => $this->company->id,
                'company_name' => 'Golden DZ',
            ],
        );

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $event = PlatformOutboxEvent::query()->firstOrFail();
        $this->assertSame(PlatformOutboxEvent::STATUS_SENT, $event->status);
        $this->assertSame(1, $event->attempts);

        $audit = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', PlatformCompanyCreatedAuditConsumer::AUDIT_ACTION)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('company.created.'.$this->company->id, $audit->metadata['event_id']);
        $this->assertSame((string) $event->id, $audit->auditable_id);
    }

    public function test_subscription_paid_event_flows_through_outbox_to_audit(): void
    {
        $this->publisher->publish(
            companyId: $this->company->id,
            eventType: PlatformSubscriptionPaidAuditConsumer::EVENT_TYPE,
            payload: [
                'event_id' => 'subscription.paid.42',
                'company_id' => $this->company->id,
                'payment_id' => '42',
                'amount' => '11900.00',
                'currency' => 'DZD',
                'status' => 'paid',
            ],
        );

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $this->assertSame(PlatformOutboxEvent::STATUS_SENT, PlatformOutboxEvent::query()->firstOrFail()->status);
        $this->assertSame(1, AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', PlatformSubscriptionPaidAuditConsumer::AUDIT_ACTION)
            ->count());
    }

    public function test_double_publish_is_deduplicated_by_idempotency_key(): void
    {
        $payload = ['event_id' => 'company.created.dup', 'company_id' => $this->company->id];

        $this->publisher->publish($this->company->id, 'platform.company.created', $payload);
        $this->publisher->publish($this->company->id, 'platform.company.created', $payload);

        $this->assertSame(1, PlatformOutboxEvent::query()->count());
    }

    public function test_spike_loses_or_duplicates_nothing(): void
    {
        // Pic simulé : 30 événements (15 company.created + 15 subscription.paid)
        // à payloads uniques, publiés en rafale.
        for ($i = 0; $i < 15; $i++) {
            $this->publisher->publish(
                $this->company->id,
                PlatformCompanyCreatedAuditConsumer::EVENT_TYPE,
                ['event_id' => "company.created.spike.{$i}", 'company_id' => $this->company->id],
            );
            $this->publisher->publish(
                $this->company->id,
                PlatformSubscriptionPaidAuditConsumer::EVENT_TYPE,
                ['event_id' => "subscription.paid.spike.{$i}", 'company_id' => $this->company->id, 'amount' => (string) $i],
            );
        }

        $this->assertSame(30, PlatformOutboxEvent::query()->count());

        $this->artisan('platform:outbox-dispatch', ['--limit' => 100]);

        // Zéro perte : les 30 sont `sent`, aucun pending/failed restant.
        $this->assertSame(30, PlatformOutboxEvent::query()->where('status', PlatformOutboxEvent::STATUS_SENT)->count());
        $this->assertSame(0, PlatformOutboxEvent::query()->where('status', '!=', PlatformOutboxEvent::STATUS_SENT)->count());

        // Zéro doublon : 30 effets d'audit exactement (un par événement).
        $audits = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->whereIn('action', [PlatformCompanyCreatedAuditConsumer::AUDIT_ACTION, PlatformSubscriptionPaidAuditConsumer::AUDIT_ACTION])
            ->count();

        $this->assertSame(30, $audits);
    }

    public function test_backpressure_limit_is_respected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->publisher->publish(
                $this->company->id,
                PlatformCompanyCreatedAuditConsumer::EVENT_TYPE,
                ['event_id' => "company.created.bp.{$i}", 'company_id' => $this->company->id],
            );
        }

        $this->artisan('platform:outbox-dispatch', ['--limit' => 3]);

        $this->assertSame(3, PlatformOutboxEvent::query()->where('status', PlatformOutboxEvent::STATUS_SENT)->count());
        $this->assertSame(7, PlatformOutboxEvent::query()->where('status', PlatformOutboxEvent::STATUS_PENDING)->count());

        // Passe suivante : la file se vide, rien n'est perdu.
        $this->artisan('platform:outbox-dispatch', ['--limit' => 100]);
        $this->assertSame(10, PlatformOutboxEvent::query()->where('status', PlatformOutboxEvent::STATUS_SENT)->count());
    }

    public function test_transient_failure_retries_with_backoff(): void
    {
        $this->registry->register(new class implements PlatformOutboxConsumer {
            public function supports(string $eventType): bool
            {
                return $eventType === 'platform.test.transient';
            }

            public function handle(array $payload): void
            {
                throw new TransientOutboxException('provider indisponible');
            }
        });

        $this->publisher->publish($this->company->id, 'platform.test.transient', ['event_id' => 't1', 'company_id' => $this->company->id]);

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $event = PlatformOutboxEvent::query()->firstOrFail();
        $this->assertSame(PlatformOutboxEvent::STATUS_PENDING, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertTrue($event->available_at->isFuture(), 'le backoff doit repousser available_at dans le futur');

        // Retry après échéance : le consommateur échoue encore → tentatives 2.
        $event->forceFill(['available_at' => now()->subMinute()])->save();
        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);
        $this->assertSame(2, PlatformOutboxEvent::query()->firstOrFail()->attempts);
    }

    public function test_permanent_failure_goes_to_dead_letter(): void
    {
        $this->registry->register(new class implements PlatformOutboxConsumer {
            public function supports(string $eventType): bool
            {
                return $eventType === 'platform.test.permanent';
            }

            public function handle(array $payload): void
            {
                throw new PermanentOutboxException('invariant violé');
            }
        });

        $this->publisher->publish($this->company->id, 'platform.test.permanent', ['event_id' => 'p1', 'company_id' => $this->company->id]);

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $event = PlatformOutboxEvent::query()->firstOrFail();
        $this->assertSame(PlatformOutboxEvent::STATUS_FAILED, $event->status);
        $this->assertStringContainsString('permanent', (string) $event->last_error);
    }

    public function test_unknown_event_type_is_dead_lettered(): void
    {
        $this->publisher->publish($this->company->id, 'platform.unknown.event', ['event_id' => 'u1', 'company_id' => $this->company->id]);

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $event = PlatformOutboxEvent::query()->firstOrFail();
        $this->assertSame(PlatformOutboxEvent::STATUS_FAILED, $event->status);
        $this->assertSame('no_consumer', $event->last_error);
    }

    public function test_replay_command_reprocesses_dead_lettered_events(): void
    {
        // 1. Dead-letter via un consommateur permanent (bascule mutable).
        $consumer = new class implements PlatformOutboxConsumer {
            public bool $shouldFail = true;

            public function supports(string $eventType): bool
            {
                return $eventType === 'platform.test.replay';
            }

            public function handle(array $payload): void
            {
                if ($this->shouldFail) {
                    throw new PermanentOutboxException('boom');
                }

                DB::table('platform_test_effects')->insert([
                    'event_type' => $payload['event_id'],
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        };

        $this->registry->register($consumer);

        $this->publisher->publish($this->company->id, 'platform.test.replay', ['event_id' => 'r1', 'company_id' => $this->company->id]);
        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);
        $this->assertSame(PlatformOutboxEvent::STATUS_FAILED, PlatformOutboxEvent::query()->firstOrFail()->status);

        // 2. Replay contrôlé : l'événement repart en pending.
        $this->artisan('platform:outbox-replay', ['--event-type' => 'platform.test.replay']);
        $event = PlatformOutboxEvent::query()->firstOrFail();
        $this->assertSame(PlatformOutboxEvent::STATUS_PENDING, $event->status);
        $this->assertSame(0, $event->attempts);

        // 3. Le consommateur corrigé réussit → sent, sans doublon.
        $consumer->shouldFail = false;
        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);
        $this->assertSame(PlatformOutboxEvent::STATUS_SENT, PlatformOutboxEvent::query()->firstOrFail()->status);
        $this->assertSame(1, DB::table('platform_test_effects')->count());
    }

    public function test_stale_processing_lease_is_reclaimed_after_worker_crash(): void
    {
        $this->registry->register(new class implements PlatformOutboxConsumer {
            public function supports(string $eventType): bool
            {
                return $eventType === 'platform.test.lease';
            }

            public function handle(array $payload): void
            {
                DB::table('platform_test_effects')->insert([
                    'event_type' => $payload['event_id'],
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        });

        $event = $this->publisher->publish($this->company->id, 'platform.test.lease', ['event_id' => 'l1', 'company_id' => $this->company->id]);

        // Simulation de crash : l'événement est resté `processing` au-delà de la lease (15 min).
        DB::table('platform_outbox_events')
            ->where('id', $event->id)
            ->update(['status' => PlatformOutboxEvent::STATUS_PROCESSING, 'updated_at' => now()->subMinutes(20)]);

        $this->artisan('platform:outbox-dispatch', ['--limit' => 10]);

        $fresh = PlatformOutboxEvent::query()->findOrFail($event->id);
        $this->assertSame(PlatformOutboxEvent::STATUS_SENT, $fresh->status);
        $this->assertSame(1, DB::table('platform_test_effects')->count());
    }

    private function createEffectsTable(): void
    {
        if (! Schema::hasTable('platform_test_effects')) {
            Schema::create('platform_test_effects', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 120);
                $table->jsonb('payload')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }
}
