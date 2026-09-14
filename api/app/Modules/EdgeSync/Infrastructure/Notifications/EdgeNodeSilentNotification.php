<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Infrastructure\Notifications;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'administrateur qu'un node Edge est silencieux depuis >30 minutes.
 */
class EdgeNodeSilentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly EdgeNode $node) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $silenceDuration = $this->node->last_seen_at?->diffForHumans() ?? '—';
        $nodeName = $this->node->name;
        $companyName = $this->node->company?->name ?? 'Entreprise inconnue';

        return (new MailMessage())
            ->subject(__('emails.edge_node_silent_subject', ['node' => $nodeName, 'company' => $companyName]))
            ->greeting(__('emails.edge_node_silent_greeting'))
            ->line(__('emails.edge_node_silent_body', ['node' => $nodeName, 'company' => $companyName, 'duration' => $silenceDuration]))
            ->line(__('emails.edge_node_silent_note'))
            ->line(__('emails.edge_node_silent_support').' '.config('mail.brand.support_address'))
            ->line(config('mail.brand.name'));
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'node_id' => $this->node->id,
            'node_name' => $this->node->name,
            'last_seen' => $this->node->last_seen_at?->toIso8601String(),
        ];
    }
}
