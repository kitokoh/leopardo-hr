<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Notification mail envoyée aux managers quand un nœud Edge
 * n'a pas envoyé de heartbeat depuis plus de N minutes.
 */
class EdgeNodeSilentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string  $nodeName,
        public readonly string  $nodeId,
        public readonly string  $companyName,
        public readonly ?Carbon $lastSeenAt,
        public readonly int     $thresholdMins,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lastSeenLabel = $this->lastSeenAt
            ? $this->lastSeenAt->diffForHumans() . ' (' . $this->lastSeenAt->format('d/m/Y H:i') . ')'
            : 'Jamais connecté';

        return (new MailMessage)
            ->subject("[Leopardo Edge] ⚠ Nœud silencieux : {$this->nodeName}")
            ->greeting("Bonjour,")
            ->line("Un nœud Edge de votre organisation **{$this->companyName}** n'a plus donné signe de vie.")
            ->line("---")
            ->line("**Nœud :** {$this->nodeName} (`{$this->nodeId}`)")
            ->line("**Dernier contact :** {$lastSeenLabel}")
            ->line("**Seuil d'alerte :** {$this->thresholdMins} minutes sans heartbeat")
            ->line("---")
            ->line("⚠ Les pointages enregistrés localement sont **mis en file d'attente** et seront synchronisés automatiquement dès le rétablissement de la connexion.")
            ->action(
                'Voir les nœuds Edge',
                url('/edge')
            )
            ->line("Si ce nœud est intentionnellement hors ligne, vous pouvez **mettre en sourdine** les alertes depuis le tableau de bord.")
            ->salutation("L'équipe Leopardo RH");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'edge_node_silent',
            'node_id'        => $this->nodeId,
            'node_name'      => $this->nodeName,
            'company_name'   => $this->companyName,
            'last_seen_at'   => $this->lastSeenAt?->toIso8601String(),
            'threshold_mins' => $this->thresholdMins,
        ];
    }
}
