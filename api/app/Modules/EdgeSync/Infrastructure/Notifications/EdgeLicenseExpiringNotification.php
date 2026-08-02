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
        $nodeName = $node?->name ?? 'inconnu';
        $companyName = $node?->company?->name ?? 'Entreprise inconnue';
        $expiresIn = $this->license->expires_at?->diffForHumans() ?? 'bientôt';
        $expiresAt = $this->license->expires_at?->format('d/m/Y') ?? '—';

        return (new MailMessage())
            ->subject("🔑 Licence Edge expirant {$expiresIn} — {$nodeName} ({$companyName})")
            ->greeting("Bonjour,")
            ->line("La licence du node Edge **{$nodeName}** ({$companyName}) expire **{$expiresAt}** ({$expiresIn}).")
            ->line("Après expiration, le node passera automatiquement en mode dégradé : les nouvelles synchronisations seront bloquées.")
            ->action('Renouveler la licence', url('/admin/edge-nodes'))
            ->line('Le renouvellement se fait automatiquement si la connexion Cloud est disponible.');
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'license_id' => $this->license->id,
            'expires_at' => $this->license->expires_at?->toIso8601String(),
        ];
    }
}
