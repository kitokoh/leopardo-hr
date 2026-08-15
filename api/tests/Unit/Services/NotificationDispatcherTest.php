<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\DeviceToken;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.firebase.project_id', 'test-project-id');
        Cache::put('firebase_access_token', 'mock-access-token', 3600);

        // La table `app_notifications` n'est créée par aucune migration du
        // repo (dette #1813) : schéma manuel local au test (pattern
        // TaxSlabValidationWorkflowTest).
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

    private function makeEmployee(): Employee
    {
        return Employee::query()->create([
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'matricule' => 'DISPATCH-01',
            'first_name' => 'Push',
            'last_name' => 'Test',
            'email' => 'push@test.test',
            'password_hash' => bcrypt('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_dispatch_creates_in_app_notification(): void
    {
        $employee = $this->makeEmployee();

        $dispatcher = new NotificationDispatcher(new PushNotificationService());

        $notification = $dispatcher->dispatch(
            $employee->id,
            'leave_approved',
            'Congé approuvé',
            'Votre demande a été validée.',
            ['leave_id' => 42],
            '/leaves/42',
        );

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame($employee->id, $notification->user_id);
        $this->assertSame('leave_approved', $notification->type);
        $this->assertFalse($notification->read);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $employee->id,
            'type' => 'leave_approved',
            'title' => 'Congé approuvé',
        ]);
    }

    public function test_dispatch_sends_push_to_active_device_token(): void
    {
        $employee = $this->makeEmployee();
        DeviceToken::query()->create([
            'employee_id' => $employee->id,
            'token' => 'fcm-token-1',
            'platform' => 'android',
            'is_active' => true,
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/test-project-id/messages:send' => Http::response(['name' => 'messages/1'], 200),
        ]);

        $dispatcher = new NotificationDispatcher(new PushNotificationService());
        $dispatcher->dispatch($employee->id, 'payroll_ready', 'Bulletin disponible', 'Votre bulletin est prêt.');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project-id/messages:send'
                && $request['message']['token'] === 'fcm-token-1'
                && $request['message']['notification']['title'] === 'Bulletin disponible';
        });
    }

    public function test_dispatch_without_device_token_still_creates_in_app_notification(): void
    {
        $employee = $this->makeEmployee();

        $dispatcher = new NotificationDispatcher(new PushNotificationService());

        $notification = $dispatcher->dispatch($employee->id, 'test_type', 'Titre', 'Corps');

        $this->assertInstanceOf(AppNotification::class, $notification);
        Http::assertNothingSent();
    }

    public function test_dispatch_fcm_failure_is_fail_open_and_traced_structured(): void
    {
        $employee = $this->makeEmployee();

        // Issue #2498 — un échec remontant du push (exception) doit être tracé
        // sur le channel structuré et rester fail-open : on pointe le channel
        // vers un fichier temporaire et on vérifie la trace écrite
        // (observabilité réelle, sans mock Mockery). Le service réel avale les
        // 4xx FCM en interne (fail-open du service), donc on simule une panne
        // remontante via une sous-classe qui jette une exception.
        $logPath = storage_path('logs/structured-'.uniqid('', true).'.log');
        Config::set('logging.channels.structured.path', $logPath);

        $failingPush = new class extends PushNotificationService {
            /** @param array<string, mixed> $data */
            public function sendToUser(int $userId, string $title, string $body, array $data = []): int
            {
                throw new \RuntimeException('FCM unavailable');
            }
        };

        try {
            $dispatcher = new NotificationDispatcher($failingPush);

            // La notification in-app est créée malgré l'échec push (fail-open).
            $notification = $dispatcher->dispatch($employee->id, 'test_type', 'Titre', 'Corps');
            $this->assertInstanceOf(AppNotification::class, $notification);

            $this->assertDatabaseHas('app_notifications', ['user_id' => $employee->id]);

            $trace = file_get_contents($logPath) ?: '';
            $this->assertStringContainsString('notification.push-skipped', $trace);
            $this->assertStringContainsString('test_type', $trace);
        } finally {
            @unlink($logPath);
        }
    }

    public function test_dispatch_create_failure_is_traced_structured_and_rethrown(): void
    {
        $employee = $this->makeEmployee();

        // Simule la dette #2398 : table absente → échec de création in-app.
        Schema::drop('app_notifications');

        // Channel `structured` pointé vers un fichier temporaire : on vérifie
        // la trace d'erreur réelle écrite (observabilité, pas de mock).
        $logPath = storage_path('logs/structured-'.uniqid('', true).'.log');
        Config::set('logging.channels.structured.path', $logPath);

        try {
            $dispatcher = new NotificationDispatcher(new PushNotificationService());

            try {
                $dispatcher->dispatch($employee->id, 'leave_approved', 'Congé approuvé', 'Corps');
                $this->fail('L’échec de création doit être relancé (contrat best-effort de l’appelant).');
            } catch (\Throwable $exception) {
                // Attendu : le dispatcher journalise (structured) puis relance.
                $this->assertStringContainsString('app_notifications', $exception->getMessage());
            }

            $trace = file_get_contents($logPath) ?: '';
            $this->assertStringContainsString('notification.inapp-create-failed', $trace);
            $this->assertStringContainsString((string) $employee->id, $trace);
            $this->assertStringContainsString('leave_approved', $trace);
        } finally {
            @unlink($logPath);
        }
    }
}
