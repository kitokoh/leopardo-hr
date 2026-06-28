<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\Notification\Application\Actions\MarkNotificationsRead;
use App\Modules\Notification\Application\Actions\SendNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_notification_action_instantiates(): void
    {
        $dispatcher = new NotificationDispatcher();
        $action = new SendNotification($dispatcher);
        $this->assertInstanceOf(SendNotification::class, $action);
    }

    public function test_mark_notifications_read_action_instantiates(): void
    {
        $action = new MarkNotificationsRead();
        $this->assertInstanceOf(MarkNotificationsRead::class, $action);
    }
}
