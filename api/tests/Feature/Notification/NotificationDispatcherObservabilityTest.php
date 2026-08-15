<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2498 — observabilité de NotificationDispatcher::dispatch().
 *
 * Le chemin legacy de notification in-app était cassé SILENCIEUSEMENT en
 * production (table `app_notifications` jamais migrée, dette #1813) : les
 * try/catch best-effort avalaient les échecs. La migration est mergée
 * (#2395/#2446), mais l'observabilité doit rester : tout échec du dispatch
 * (persistance in-app OU push FCM) produit une entrée de log structuré
 * (channel `structured`) avec contexte — sans rendre le dispatch bloquant.
 */
class NotificationDispatcherObservabilityTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Table créée par la migration tenant 2026_08_15_000001 ; garde
        // défensive pour les environnements partiellement migrés (même
        // pattern que AppNotificationRelationTest).
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 100)->index();
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->jsonb('data')->nullable();
                $table->boolean('read')->default(false);
                $table->timestampTz('read_at')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->timestampsTz();
                $table->index(['user_id', 'read']);
            });
        }
    }

    protected function tearDown(): void
    {
        // Le listener `saving` du test d'échec de persistance ne doit pas
        // fuiter vers les autres tests du process.
        AppNotification::flushEventListeners();

        parent::tearDown();
    }

    private function dispatcherWith(PushNotificationService $push): NotificationDispatcher
    {
        return new NotificationDispatcher($push);
    }

    public function test_push_failure_is_logged_structured_but_does_not_break_dispatch(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // Échec simulé du push FCM (best-effort conservé : le dispatch in-app
        // doit réussir malgré tout, et l'échec doit être traçable).
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')
            ->once()
            ->andThrow(new \RuntimeException('FCM transport down'));

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')
            ->once()
            ->with('notification.push-skipped', Mockery::on(function (array $context): bool {
                return $context['event'] === 'notification.push'
                    && $context['notification_type'] === 'tax_rate_validation'
                    && is_int($context['user_id'])
                    && $context['error'] === 'FCM transport down'
                    && isset($context['exception_class']);
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('structured')
            ->andReturn($logger);

        $notification = $this->dispatcherWith($push)->dispatch(
            (int) $employee->id,
            'tax_rate_validation',
            'Validation requise',
            'Un taux nécessite votre validation',
            ['table' => 'tax_slabs', 'record_id' => 42],
        );

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame('tax_rate_validation', $notification->type);
        $this->assertSame((int) $employee->id, (int) $notification->user_id);
        $this->assertFalse($notification->read);
    }

    public function test_inapp_persist_failure_is_logged_structured_and_rethrown(): void
    {
        // Échec simulé de la persistance in-app (la classe de bug #1813 :
        // table absente / schéma en dérive). Déterministe via l'événement
        // Eloquent `saving` — aucun impact sur le schéma de test.
        AppNotification::saving(function (): void {
            throw new \RuntimeException('app_notifications write failed (simulated)');
        });

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->never();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with('notification.dispatch-failed', Mockery::on(function (array $context): bool {
                return $context['event'] === 'notification.dispatch'
                    && $context['notification_type'] === 'tax_rate_validation'
                    && $context['user_id'] === 7
                    && $context['error'] === 'app_notifications write failed (simulated)'
                    && $context['exception_class'] === \RuntimeException::class;
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('structured')
            ->andReturn($logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app_notifications write failed (simulated)');

        $this->dispatcherWith($push)->dispatch(
            7,
            'tax_rate_validation',
            'Validation requise',
            null,
        );
    }

    public function test_successful_dispatch_writes_no_error_log(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->once()->andReturn(1);

        // Aucun appel au channel structured sur le chemin nominal (ni error
        // ni warning) — l'observabilité ne doit pas bruiter le succès.
        Log::shouldReceive('channel')->with('structured')->never();

        $notification = $this->dispatcherWith($push)->dispatch(
            (int) $employee->id,
            'payroll_validated',
            'Paie validée',
            null,
        );

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame('payroll_validated', $notification->type);
    }
}
