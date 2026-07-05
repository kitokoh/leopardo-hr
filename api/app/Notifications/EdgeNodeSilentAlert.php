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
 * Utilisee par la commande Artisan `edge:detect-silent-nodes`
 * (App\Console\Commands\DetectSilentEdgeNodes).
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
        $silenceDuration = $this->lastSeenAt?->diffForHumans() ?? 'Jamais vu';

        return (new MailMessage())
            ->subject("⚠️ Node Edge silencieux — {$this->nodeName} ({$this->companyName})")
            ->greeting('Bonjour,')
            ->line("Le node Edge **{$this->nodeName}** de l'entreprise **{$this->companyName}** n'a pas communiqué depuis **{$silenceDuration}** (seuil configuré : {$this->thresholdMins} min).")
            ->line('Les pointages effectués pendant cette période seront synchronisés automatiquement au retour de la connexion.')
            ->action('Voir les nodes Edge', url('/admin/edge-nodes'))
            ->line('Si le problème persiste, vérifiez la connectivité réseau du node ou contactez le support Leopardo.');
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
