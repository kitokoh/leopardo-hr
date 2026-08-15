<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Application\Actions\SendNotification;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Mockery;
use Mockery\MockInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issues #2200/#2230 — NotificationDispatcher::dispatch() ne déclenchait
 * jamais le push FCM (TODO) alors que PushNotificationService existe.
 *
 * Le push est désormais tenté best-effort après la création de la
 * notification in-app : appelé quand des device tokens existent, et un
 * échec de push ne bloque jamais la notification.
 */
class NotificationPushDispatchTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dispatch_triggers_push_to_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var PushNotificationService&MockInterface $push */
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToEmployee')
            ->once()
            ->with(Mockery::on(fn (Employee $e): bool => $e->id === $employee->id), 'Nouveau bulletin', 'Votre bulletin est prêt', ['type' => 'pay_slip'])
            ->andReturn(1);

        $this->app->instance(PushNotificationService::class, $push);

        /** @var SendNotification $action */
        $action = $this->app->make(SendNotification::class);

        $notification = $action->handle(
            $employee->id,
            'pay_slip',
            'Nouveau bulletin',
            'Votre bulletin est prêt',
            ['type' => 'pay_slip'],
        );

        $this->assertSame('Nouveau bulletin', $notification->title);
        $this->assertFalse($notification->read);
    }

    public function test_dispatch_unknown_user_skips_push_but_creates_notification(): void
    {
        /** @var PushNotificationService&MockInterface $push */
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToEmployee');

        $this->app->instance(PushNotificationService::class, $push);

        /** @var SendNotification $action */
        $action = $this->app->make(SendNotification::class);

        // userId 999999 n'existe pas : pas de push, mais la notification est
        // quand même créée (le dispatcher ne dépend pas de l'existence de
        // l'employé pour persister la notification in-app).
        $notification = $action->handle(999_999, 'info', 'Notification orpheline', 'corps');

        $this->assertSame('Notification orpheline', $notification->title);
    }

    public function test_push_failure_is_non_blocking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var PushNotificationService&MockInterface $push */
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToEmployee')
            ->once()
            ->andThrow(new \RuntimeException('Firebase timeout'));

        $this->app->instance(PushNotificationService::class, $push);

        /** @var SendNotification $action */
        $action = $this->app->make(SendNotification::class);

        // Le push échoue (exception) : la notification doit quand même
        // exister et le dispatch ne doit pas propager l'erreur.
        $notification = $action->handle($employee->id, 'alert', 'Alerte', 'corps');

        $this->assertSame('Alerte', $notification->title);
    }
}
