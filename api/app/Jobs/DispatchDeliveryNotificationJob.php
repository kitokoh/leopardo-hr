<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Delivery\Domain\Contracts\RecipientMessageContract;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envoi asynchrone d'une notification destinataire (DELIVERY-206, issue
 * #6290) — tenant-scoped (pattern GenerateBankExportJob), retry borné (3),
 * passage sent/failed ; aucune PII dans les logs.
 */
class DispatchDeliveryNotificationJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var DeliveryNotification|null $notification */
        $notification = DeliveryNotification::query()->withoutGlobalScopes()->find($this->notificationId);

        return $this->resolvedCompanyId = $notification?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(RecipientMessageContract $sender): void
    {
        /** @var DeliveryNotification|null $notification */
        $notification = DeliveryNotification::query()->withoutGlobalScopes()->find($this->notificationId);

        if ($notification === null) {
            Log::channel('structured')->warning('delivery.notification.missing', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        if ($notification->status === 'skipped') {
            return; // opt-out — jamais envoyé
        }

        try {
            $sent = $sender->send(
                $notification->recipient_phone,
                $notification->template_key,
                $notification->payload ?? [],
            );

            $notification->forceFill([
                'status' => $sent ? 'sent' : 'failed',
                'attempts' => $notification->attempts + 1,
                'sent_at' => $sent ? now() : $notification->sent_at,
            ])->save();
        } catch (\Throwable $exception) {
            $notification->forceFill([
                'status' => 'failed',
                'attempts' => $notification->attempts + 1,
            ])->save();

            Log::channel('structured')->error('delivery.notification.failed', [
                'notification_id' => $this->notificationId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception; // retry borné (tries=3) puis DLQ
        }
    }
}
