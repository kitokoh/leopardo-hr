<?php

namespace App\Modules\EdgeSync\Notifications;

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
        $silenceDuration = $this->node->last_seen_at?->diffForHumans() ?? 'inconnu';
        $nodeName = $this->node->name;
        $companyName = $this->node->company?->name ?? 'Entreprise inconnue';

        return (new MailMessage())
            ->subject("⚠️ Node Edge silencieux — {$nodeName} ({$companyName})")
            ->greeting("Bonjour,")
            ->line("Le node Edge **{$nodeName}** de l'entreprise **{$companyName}** n'a pas communiqué depuis **{$silenceDuration}**.")
            ->line('Les pointages effectués pendant cette période seront synchronisés automatiquement au retour de la connexion.')
            ->action('Voir les nodes Edge', url('/admin/edge-nodes'))
            ->line('Si le problème persiste, vérifiez la connectivité réseau du node ou contactez le support Leopardo.');
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'node_id'   => $this->node->id,
            'node_name' => $this->node->name,
            'last_seen' => $this->node->last_seen_at?->toIso8601String(),
        ];
    }
}
