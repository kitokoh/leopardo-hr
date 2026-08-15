<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\SendPushNotificationJob;
use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2252 — le dispatcher legacy émet désormais le push FCM/APNs
 * (SendPushNotificationJob) après la création de la notification in-app.
 *
 * La table `app_notifications` n'est créée par aucune migration du repo
 * (dette #1813) : schéma manuel local au test (pattern
 * TaxSlabValidationWorkflowTest) pour prouver la chaîne de bout en bout.
 */
class NotificationDispatcherPushTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_notifications', function (Blueprint $table): void {
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

    public function test_dispatch_creates_in_app_notification(): void
    {
        $dispatcher = new NotificationDispatcher();

        $notification = $dispatcher->dispatch(42, 'test_type', 'Hello', 'Body', ['k' => 'v']);

        $this->assertInstanceOf(AppNotification::class, $notification);
        $this->assertSame(42, (int) $notification->user_id);
        $this->assertSame('test_type', $notification->type);
        $this->assertSame('Hello', $notification->title);
        $this->assertSame(['k' => 'v'], $notification->data);
        $this->assertFalse($notification->read);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => 42,
            'type' => 'test_type',
            'title' => 'Hello',
            'read' => false,
        ]);
    }

    public function test_dispatch_queues_push_notification_job_with_metadata(): void
    {
        Queue::fake();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(7, 'test_type', 'Title', 'Body', ['record_id' => 5], '/action');

        Queue::assertPushed(SendPushNotificationJob, function (SendPushNotificationJob $job) {
            return $job->employeeId === 7
                && $job->title === 'Title'
                && $job->body === 'Body'
                && $job->metadata === ['record_id' => 5, 'action_url' => '/action'];
        });
    }

    public function test_dispatch_without_action_url_queues_plain_metadata(): void
    {
        Queue::fake();

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(8, 'test_type', 'T', null, ['a' => 1]);

        Queue::assertPushed(SendPushNotificationJob, function (SendPushNotificationJob $job) {
            return $job->employeeId === 8 && $job->metadata === ['a' => 1];
        });
    }
}
