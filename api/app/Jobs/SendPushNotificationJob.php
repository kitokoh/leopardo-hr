<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public int $employeeId,
        public string $title,
        public string $body,
        public array $metadata = []
    ) {
        $this->onQueue((string) config('communication.queue', 'notifications'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        $employee = Employee::find($this->employeeId);

        if ($employee) {
            $pushService->sendToEmployee($employee, $this->title, $this->body, $this->metadata);
        }
    }
}
