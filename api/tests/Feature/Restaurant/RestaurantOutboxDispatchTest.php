<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxConsumerRegistry;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-808 (#6229) — Dispatcher outbox RestaurantManager.
 *
 * Couvre : consommation des événements dus (claim atomique, statut published),
 * dead-letter sans consommateur, retry avec backoff sur erreur transitoire,
 * et itération multi-tenants.
 */
class RestaurantOutboxDispatchTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dispatcher_consumes_events_and_marks_published(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        // Équipe cuisine cible de la notification.
        Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);

        // CommunicationService mocké : le test porte sur le dispatcher, pas
        // sur l'émission réelle des notifications (BC-13).
        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldReceive('notifyEmployee')->andReturn([]);
        $this->app->instance(CommunicationService::class, $communication);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'status' => 'open',
            'currency' => 'XAF',
        ]);

        app(RestaurantOutboxPublisher::class)->publish(
            $company->id,
            'restaurant.order.created.v1',
            ['order_id' => $order->id, 'reference' => $order->reference, 'branch_id' => $order->branch_id],
        );

        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        $this->assertSame(
            1,
            RestaurantOutboxEvent::query()->where('status', RestaurantOutboxEvent::STATUS_PUBLISHED)->count(),
            'L\'événement consommé passe à published.'
        );
    }

    public function test_dispatcher_dead_letters_events_without_consumer(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(RestaurantOutboxPublisher::class)->publish(
            $company->id,
            'restaurant.unknown.v1',
            ['nothing' => true],
        );

        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        /** @var RestaurantOutboxEvent $event */
        $event = RestaurantOutboxEvent::query()->where('event_type', 'restaurant.unknown.v1')->firstOrFail();

        $this->assertSame(RestaurantOutboxEvent::STATUS_FAILED, $event->status);
        $this->assertStringContainsString('no_consumer', (string) $event->last_error);
    }

    public function test_dispatcher_retries_on_transient_failure(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        // Consommateur de test qui échoue toujours (erreur transitoire).
        $throwing = new class implements RestaurantOutboxConsumer
        {
            public function supports(string $eventType): bool
            {
                return $eventType === 'restaurant.test.flaky.v1';
            }

            public function handle(array $payload): void
            {
                throw new \RuntimeException('boom transitoire');
            }
        };

        app(RestaurantOutboxConsumerRegistry::class)->register($throwing);

        app(RestaurantOutboxPublisher::class)->publish(
            $company->id,
            'restaurant.test.flaky.v1',
            ['x' => 1],
        );

        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        /** @var RestaurantOutboxEvent $event */
        $event = RestaurantOutboxEvent::query()->where('event_type', 'restaurant.test.flaky.v1')->firstOrFail();

        $this->assertSame(RestaurantOutboxEvent::STATUS_PENDING, $event->status, 'Erreur transitoire → retry (pending).');
        $this->assertSame(1, $event->attempts);
        $this->assertTrue($event->available_at->isFuture(), 'Backoff appliqué.');
        $this->assertStringContainsString('boom transitoire', (string) $event->last_error);
    }

    public function test_dispatcher_iterates_tenants(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(RestaurantOutboxPublisher::class)->publish($companyA->id, 'restaurant.unknown.v1', ['a' => 1]);
        app(RestaurantOutboxPublisher::class)->publish($companyB->id, 'restaurant.unknown.v1', ['b' => 1]);

        $this->artisan('restaurant:outbox-dispatch', ['--limit' => 50])->assertSuccessful();

        $this->assertSame(
            2,
            RestaurantOutboxEvent::query()->where('status', RestaurantOutboxEvent::STATUS_FAILED)->count(),
            'Les événements des deux tenants sont traités.'
        );
    }
}
