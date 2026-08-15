<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Domain\Models\DeviceToken;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T005 (#2230).
 *
 * NotificationDispatcher déclenche le push FCM via PushNotificationService :
 * avec token actif (envoi), sans token (0, notification in-app quand même),
 * échec FCM (token désactivé).
 */
class NotificationPushDispatchTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table `app_notifications` n'est créée par aucune migration du
        // repo (dette #1813) : schéma manuel local au test (pattern existant).
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function ($table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 80);
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->jsonb('data')->nullable();
                $table->boolean('read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->timestampsTz();
            });
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeEmployee(Company $company): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_dispatch_sends_push_when_employee_has_active_token(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        DeviceToken::query()->create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'token' => 'fcm-token-1',
            'platform' => 'android',
            'is_active' => true,
        ]);

        // Swap le vrai service par un spy pour isoler le wiring du dispatcher.
        $push = $this->createMock(PushNotificationService::class);
        $push->expects($this->once())
            ->method('sendToEmployee')
            ->with(
                $this->callback(fn (Employee $e): bool => $e->id === $employee->id),
                'Congé approuvé',
                'Votre congé est approuvé',
                ['absence_id' => 7]
            )
            ->willReturn(1);

        $this->app->instance(PushNotificationService::class, $push);

        /** @var NotificationDispatcher $dispatcher */
        $dispatcher = app(NotificationDispatcher::class);

        $notification = $dispatcher->dispatch(
            $employee->id,
            'absence.approved',
            'Congé approuvé',
            'Votre congé est approuvé',
            ['absence_id' => 7],
        );

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame($employee->id, $notification->user_id);
        $this->assertFalse($notification->read);
    }

    public function test_dispatch_creates_in_app_notification_without_tokens(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        // Vrai service : aucun token → sendToEmployee retourne 0, la
        // notification in-app est quand même créée.
        $dispatcher = app(NotificationDispatcher::class);

        $notification = $dispatcher->dispatch($employee->id, 'test', 'Titre', 'Corps');

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $employee->id,
            'type' => 'test',
            'title' => 'Titre',
        ]);
    }

    public function test_fcm_failure_deactivates_token(): void
    {
        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        DeviceToken::query()->create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'token' => 'fcm-token-dead',
            'platform' => 'ios',
            'is_active' => true,
        ]);

        config()->set('services.firebase.project_id', 'test-project');

        // Token d'accès pré-câché : évite le flux OAuth JWT (signature
        // openssl) dans le test et isole le comportement FCM testé.
        Cache::put('firebase_access_token', 'test-access-token', 3000);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
        ]);

        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->dispatch($employee->id, 'test', 'Titre', 'Corps');

        $token = DeviceToken::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($token);
        $this->assertFalse((bool) $token->is_active);
    }
}
