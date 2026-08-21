<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\Config;
use Illuminate\Testing\PendingCommand;
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

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('mail:check-config');
        $cmd->expectsOutputToContain('array');
        $cmd->assertExitCode(0);
        $cmd->run();
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

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('mail:check-config');
        $cmd->expectsOutputToContain('MAILGUN_DOMAIN absent/vide');
        $cmd->expectsOutputToContain('MAILGUN_SECRET absent/vide');
        $cmd->assertExitCode(1);
        $cmd->run();
    }

    public function test_flags_missing_from_address(): void
    {
        Config::set('mail.default', 'array');
        Config::set('mail.from.address', '');

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('mail:check-config');
        $cmd->expectsOutputToContain('MAIL_FROM_ADDRESS absent/vide');
        $cmd->assertExitCode(1);
        $cmd->run();
    }
}
