<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Issue #5162 — `mail:check-config` : le trial OTP échouait en prod avec
 * 503 TRIAL_OTP_SEND_FAILED sans visibilité sur la cause (config Mailgun
 * absente sur Render). La commande expose le transport résolu et les
 * variables requises (présence seule, jamais les secrets) pour un triage
 * immédiat en ops.
 */
class MailCheckConfigCommandTest extends TestCase
{
    public function test_reports_resolved_mailer_and_success_when_config_ok(): void
    {
        Config::set('mail.default', 'array');

        $this->artisan('mail:check-config')
            ->expectsOutputToContain('array')
            ->assertExitCode(0);
    }

    public function test_flags_missing_mailgun_credentials_when_transport_is_mailgun(): void
    {
        // Simulation de l'env Render défaillant (#5162) : transport mailgun
        // mais MAILGUN_DOMAIN/MAILGUN_SECRET absents → envoi impossible.
        Config::set('mail.default', 'mailgun');
        Config::set('mail.mailers.mailgun', [
            'transport' => 'mailgun',
            'domain' => null,
            'secret' => null,
            'endpoint' => 'api.mailgun.net',
        ]);

        $this->artisan('mail:check-config')
            ->expectsOutputToContain('MAILGUN_DOMAIN absent/vide')
            ->expectsOutputToContain('MAILGUN_SECRET absent/vide')
            ->assertExitCode(1);
    }

    public function test_flags_missing_from_address(): void
    {
        Config::set('mail.default', 'array');
        Config::set('mail.from.address', '');

        $this->artisan('mail:check-config')
            ->expectsOutputToContain('MAIL_FROM_ADDRESS absent/vide')
            ->assertExitCode(1);
    }
}
