<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Outbox;

use App\Core\Outbox\Infrastructure\DatabaseOutboxPublisher;
use App\Core\Outbox\Infrastructure\OutboxTableResolver;
use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Modele pointant vers une table inexistante : sert a provoquer une vraie
 * erreur SQL (et non une exception PHP avant requete), le seul cas qui
 * laissait la transaction appelante avortee en 25P02.
 */
final class MissingTableOutboxEvent extends Model
{
    protected $table = 'missing_outbox_events';
}

/**
 * #8209 — Outbox du core : les deux garanties qui manquaient aux 5 copies.
 *
 * Ces tests verifient le COMPORTEMENT en base, pas la forme du code : c'est
 * la seule facon d'etablir que la transaction de l'appelant survit a un echec
 * d'outbox (la regression #6958 ne se voit pas en relisant le code).
 */
class DatabaseOutboxPublisherTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_publishes_event_into_module_outbox(): void
    {
        $event = (new DatabaseOutboxPublisher)->publish(
            CrmOutboxEvent::class,
            '11111111-1111-1111-1111-111111111111',
            'lead.created',
            ['lead_id' => 1],
        );

        $this->assertInstanceOf(CrmOutboxEvent::class, $event);
        $this->assertSame('lead.created', $event->event_type);
        $this->assertSame('pending', $event->status);
        $this->assertSame(['lead_id' => 1], $event->payload);
    }

    public function test_same_event_payload_is_not_duplicated(): void
    {
        $publisher = new DatabaseOutboxPublisher;
        $companyId = '22222222-2222-2222-2222-222222222222';

        $first = $publisher->publish(CrmOutboxEvent::class, $companyId, 'lead.created', ['lead_id' => 42]);
        $second = $publisher->publish(CrmOutboxEvent::class, $companyId, 'lead.created', ['lead_id' => 42]);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(
            1,
            DB::table($this->qualified('crm_outbox_events'))->count(),
            'Deux publications identiques doivent produire une seule ligne.',
        );
    }

    public function test_distinct_payloads_produce_distinct_events(): void
    {
        $publisher = new DatabaseOutboxPublisher;
        $companyId = '33333333-3333-3333-3333-333333333333';

        $a = $publisher->publish(CrmOutboxEvent::class, $companyId, 'lead.created', ['lead_id' => 1]);
        $b = $publisher->publish(CrmOutboxEvent::class, $companyId, 'lead.created', ['lead_id' => 2]);

        $this->assertNotSame($a->getKey(), $b->getKey());
    }

    public function test_works_for_another_module_outbox(): void
    {
        // Le meme core sert les verticales : la table vient du modele.
        $event = (new DatabaseOutboxPublisher)->publish(
            TravelOutboxEvent::class,
            '44444444-4444-4444-4444-444444444444',
            'booking.confirmed',
            ['booking_id' => 7],
        );

        $this->assertInstanceOf(TravelOutboxEvent::class, $event);
        $this->assertSame('booking.confirmed', $event->event_type);

        $this->assertSame(
            1,
            DB::table($this->qualified('travel_outbox_events'))->count(),
            'L evenement doit atterrir dans l outbox du module appele.',
        );
    }

    public function test_failed_publish_does_not_poison_caller_transaction(): void
    {
        $publisher = new DatabaseOutboxPublisher;

        DB::beginTransaction();

        try {
            // Table inexistante : la requete echoue cote SQL, exactement le cas
            // qui laissait la transaction appelante avortee en 25P02.
            $failed = false;
            try {
                $publisher->publish(
                    MissingTableOutboxEvent::class,
                    '55555555-5555-5555-5555-555555555555',
                    'boom',
                    [],
                );
            } catch (\Throwable) {
                $failed = true; // attendu : le core remonte l'erreur brute
            }

            $this->assertTrue($failed, 'Une table inexistante doit lever.');

            // LA garantie qui manquait aux 5 copies : la transaction appelante
            // doit rester utilisable apres l'echec.
            DB::statement('SELECT 1');
            $this->addToAssertionCount(1);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->fail('La transaction appelante a ete empoisonnee : '.$e->getMessage());
        }
    }

    public function test_resolver_targets_shared_tenants_schema(): void
    {
        $qualified = $this->qualified('crm_outbox_events');

        // La table vit dans shared_tenants : dependre du search_path la rate
        // (c'etait le defaut corrige en production le 2026-09-14).
        $this->assertSame('shared_tenants.crm_outbox_events', $qualified);
    }

    private function qualified(string $table): string
    {
        return (new OutboxTableResolver)->qualified($table);
    }
}
