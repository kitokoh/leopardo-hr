<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Alerte envoyee aux managers d'un tenant quand un node Edge (kiosque)
 * n'a pas envoye de heartbeat depuis plus de `thresholdMins` minutes.
 *
 * Historiquement utilisee par la commande `edge:detect-silent-nodes`
 * (supprimee — #4317).
 */
class EdgeNodeSilentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $nodeName,
        public readonly string $nodeId,
        public readonly string $companyName,
        public readonly ?Carbon $lastSeenAt,
        public readonly int $thresholdMins,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $silenceDuration = $this->lastSeenAt?->diffForHumans() ?? '—';

        return (new MailMessage)
            ->subject(__('emails.edge_node_silent_subject', ['node' => $this->nodeName, 'company' => $this->companyName]))
            ->greeting(__('emails.edge_node_silent_greeting'))
            ->line(__('emails.edge_node_silent_body', ['node' => $this->nodeName, 'company' => $this->companyName, 'duration' => $silenceDuration]))
            ->line(__('emails.edge_node_silent_note'))
            ->line(__('emails.edge_node_silent_support').' '.config('mail.brand.support_address'))
            ->line(config('mail.brand.name'));
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'edge_node_silent',
            'node_id' => $this->nodeId,
            'node_name' => $this->nodeName,
            'company_name' => $this->companyName,
            'last_seen_at' => $this->lastSeenAt?->toIso8601String(),
            'threshold_mins' => $this->thresholdMins,
        ];
    }
}
