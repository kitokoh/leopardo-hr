<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Infrastructure\Notifications;

use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'administrateur qu'une licence Edge expire dans moins de 7 jours.
 */
class EdgeLicenseExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly EdgeLicense $license) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $node = $this->license->edgeNode;
        $nodeName = $node?->name ?? '—';
        $companyName = $node?->company?->name ?? '—';
        $expiresAt = $this->license->expires_at?->format('d/m/Y') ?? '—';

        return (new MailMessage)
            ->subject(__('emails.edge_license_expiring_subject', ['node' => $nodeName, 'company' => $companyName]))
            ->greeting(__('emails.edge_node_silent_greeting'))
            ->line(__('emails.edge_license_expiring_body', ['node' => $nodeName, 'company' => $companyName, 'date' => $expiresAt]))
            ->line(__('emails.edge_license_expiring_support').' '.config('mail.brand.support_address'));
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'license_id' => $this->license->id,
            'expires_at' => $this->license->expires_at?->toIso8601String(),
        ];
    }
}
