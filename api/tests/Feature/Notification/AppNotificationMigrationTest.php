<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\Notification;
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

    /**
     * #7481 — le dispatcher publie désormais dans le store CANONIQUE
     * (`notifications`), celui que sert `GET /notifications`. Écrire dans
     * `app_notifications` produisait une notification invisible pour le web et
     * le mobile (l'API ne lit pas cette table) et hors politique (ni
     * préférences, ni heures calmes, ni quotas, ni audit).
     */
    public function test_dispatcher_creates_in_app_notification_in_the_canonical_store(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $dispatcher = new NotificationDispatcher(new PushNotificationService);

        $notification = $dispatcher->dispatch(
            userId: $employee->id,
            type: 'payroll_ready',
            title: 'Bulletin disponible',
            body: 'Votre bulletin de juin est prêt.',
            data: ['payroll_run_id' => 7],
            actionUrl: '/payroll/7',
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertSame($employee->id, (int) $notification->employee_id);
        $this->assertSame($company->id, (string) $notification->company_id);
        $this->assertSame('payroll_ready', $notification->type);
        $this->assertSame('Bulletin disponible', $notification->title);
        $this->assertFalse($notification->is_read);
        // `action_url` n'a pas de colonne dédiée : elle voyage dans `data`.
        $this->assertSame(7, $notification->data['payroll_run_id']);
        $this->assertSame('/payroll/7', $notification->data['action_url']);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'employee_id' => $employee->id,
            'type' => 'payroll_ready',
        ]);

        // Aucune écriture parallèle dans le store déprécié.
        $this->assertDatabaseCount('app_notifications', 0);
    }
}
