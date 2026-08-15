<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services\Providers;

use App\Contracts\Communication\MessageProviderInterface;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Log;

class AuditMessageProvider implements MessageProviderInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Employee $employee, string $title, string $body, array $metadata = []): string
    {
        Log::warning('Communication provider audit-only — message NOT delivered', [
            'employee_id' => $employee->id,
            'title' => $title,
            'metadata' => $metadata,
            'body_length' => strlen($body),
        ]);

        // Statut explicite de non-délivrance : les appelants ne doivent pas
        // interpréter ceci comme un envoi réussi (cf. T131 — fallback audit).
        return 'undelivered';
    }
}
