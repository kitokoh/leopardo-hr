<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Envoie une alerte Slack via webhook pour les evenements critiques.
 *
 * Usage:
 *   Notification::route('slack', config('services.slack.monitoring_webhook'))
 *       ->notify(new SlackAlertNotification('Queue backup > 100 jobs', 'warning', ['queue' => 'payroll', 'count' => 150]));
 */
class SlackAlertNotification extends Notification
{
    use Queueable;

    private const SEVERITY_EMOJI = [
        'critical' => ':rotating_light:',
        'warning' => ':warning:',
        'info' => ':information_source:',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $message,
        private readonly string $severity = 'warning',
        private readonly array $context = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlack(object $notifiable): array
    {
        $emoji = self::SEVERITY_EMOJI[$this->severity] ?? ':bell:';
        $env = config('app.env', 'unknown');
        $text = "{$emoji} *[Leopardo RH — {$env}]* {$this->message}";

        if ($this->context !== []) {
            $text .= "\n```\n".json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```";
        }

        return [
            'text' => $text,
        ];
    }
}
