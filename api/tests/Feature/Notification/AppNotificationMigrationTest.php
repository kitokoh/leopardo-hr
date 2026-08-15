<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * La table `app_notifications` n'était créée par aucune migration du repo
 * (dette #1813) : `NotificationDispatcher::dispatch()` écrivait dans une
 * table inexistante sur base fraîche (exception avalée par les try/catch
 * best-effort → notifications in-app silencieusement perdues).
 *
 * Cette suite verrouille la migration `create_app_notifications_table` et
 * le cycle de dispatch complet SANS schéma manuel local.
 */
class AppNotificationMigrationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_app_notifications_table_exists_after_tenant_migrations(): void
    {
        $this->assertTrue(
            Schema::hasTable('app_notifications'),
            'la migration tenant create_app_notifications_table doit exister (dette #1813)'
        );
    }

    public function test_dispatcher_creates_in_app_notification_without_manual_schema(): void
    {
        $dispatcher = new NotificationDispatcher(new PushNotificationService());

        $notification = $dispatcher->dispatch(
            userId: 42,
            type: 'payroll_ready',
            title: 'Bulletin disponible',
            body: 'Votre bulletin de juin est prêt.',
            data: ['payroll_run_id' => 7],
            actionUrl: '/payroll/7',
        );

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame(42, (int) $notification->user_id);
        $this->assertSame('payroll_ready', $notification->type);
        $this->assertSame('Bulletin disponible', $notification->title);
        $this->assertSame(['payroll_run_id' => 7], $notification->data);
        $this->assertFalse($notification->read);

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notification->id,
            'user_id' => 42,
            'type' => 'payroll_ready',
            'action_url' => '/payroll/7',
        ]);
    }
}
