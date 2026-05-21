<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\MessageProviderInterface;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

class AuditMessageProvider implements MessageProviderInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Employee $employee, string $title, string $body, array $metadata = []): string
    {
        Log::info('Communication provider audit-only dispatch', [
            'employee_id' => $employee->id,
            'title' => $title,
            'metadata' => $metadata,
            'body_length' => strlen($body),
        ]);

        return 'queued';
    }
}
