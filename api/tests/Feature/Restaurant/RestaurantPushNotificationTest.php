<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\RestaurantManager\Application\Consumers\KitchenOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Consumers\ServiceOrderNotificationConsumer;
use App\Modules\RestaurantManager\Application\Observers\RestaurantOrderObserver;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-808 (#6229) — Notifications push cuisine/service.
 *
 * Couvre : consommateur `order.created.v1` → équipe cuisine,
 * consommateur `order.ready.v1` → équipe service (via CommunicationService,
 * canaux app/push) et l'observateur qui émet `restaurant.order.ready.v1`
 * quand une commande passe à `ready`.
 */
class RestaurantPushNotificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_kitchen_consumer_notifies_kitchen_staff_on_new_order(): void
    {
        $company = $this->company();

        /** @var Employee $kitchen */
        $kitchen = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);

        /** @var Employee $server */
        $server = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'status' => 'open',
            'currency' => 'XAF',
        ]);

        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldReceive('notifyEmployee')->once()->withArgs(
            fn (Employee $employee): bool => $employee->id === $kitchen->id
        )->andReturn([]);

        $consumer = new KitchenOrderNotificationConsumer($communication);
        $consumer->handle(['order_id' => $order->id]);

        // `once()` garantit qu'un seul employé (le cuisinier) est notifié :
        // le serveur ne reçoit pas la notification de nouvelle commande.
        $this->addToAssertionCount(1);
    }

    public function test_service_consumer_notifies_service_staff_when_order_ready(): void
    {
        $company = $this->company();

        /** @var Employee $server */
        $server = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'kitchen',
        ]);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'status' => 'ready',
            'currency' => 'XAF',
        ]);

        $communication = Mockery::mock(CommunicationService::class);
        $communication->shouldReceive('notifyEmployee')->once()->withArgs(
            fn (Employee $employee): bool => $employee->id === $server->id
        )->andReturn([]);

        $consumer = new ServiceOrderNotificationConsumer($communication);
        $consumer->handle(['order_id' => $order->id]);

        $this->addToAssertionCount(1);
    }

    public function test_order_observer_publishes_ready_event_once(): void
    {
        $company = $this->company();

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'status' => 'in_preparation',
            'currency' => 'XAF',
        ]);

        // Transition vers ready → l'observateur publie l'événement.
        $order->forceFill(['status' => 'ready'])->save();

        $this->assertSame(
            1,
            RestaurantOutboxEvent::query()
                ->where('company_id', $company->id)
                ->where('event_type', RestaurantOrderObserver::EVENT_ORDER_READY)
                ->count(),
            'L\'événement restaurant.order.ready.v1 est publié une fois.'
        );

        // Re-sauvegarde sans changement de statut → aucun nouvel événement.
        $order->touch();
        $this->assertSame(
            1,
            RestaurantOutboxEvent::query()
                ->where('company_id', $company->id)
                ->where('event_type', RestaurantOrderObserver::EVENT_ORDER_READY)
                ->count(),
            'Pas de re-publication sans transition de statut.'
        );
    }
}
