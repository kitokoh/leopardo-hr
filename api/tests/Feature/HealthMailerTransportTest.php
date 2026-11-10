<?php

namespace Tests\Feature;

use App\Core\Mail\MailTransportDiagnostics;
use Tests\TestCase;

/**
 * #9317 — la sonde mail doit sonder le transport RÉELLEMENT résolu.
 *
 * Constat live du 2026-11-10 : `POST /api/v1/trial/signup` répondait 503
 * `TRIAL_OTP_SEND_FAILED` pendant que `GET /health/ready` rapportait
 * `mail: {ok: true, status: "skipped", mailer: "resend"}`.
 *
 * La cause n'était pas le transport mais le DIAGNOSTIC : `checkMail()` ne
 * sondait que `smtp` (`if ($mailer !== 'smtp') return skipped`). Le transport
 * de production (`resend`) tombait donc dans le `skipped` et un mailer cassé
 * produisait un faux vert — exactement le trou que #7385 prétendait combler.
 *
 * Ces tests verrouillent l'invariant : un transport réseau non local est
 * TOUJOURS sondé, et une chaîne d'envoi incomplète n'est JAMAIS `skipped`.
 */
class HealthMailerTransportTest extends TestCase
{
    /**
     * Résolveur DNS déterministe : aucun test ne doit dépendre du réseau.
     *
     * @param  list<string>  $resolving  domaines qui « existent »
     */
    private function fakeDns(array $resolving): void
    {
        $this->app->instance(MailTransportDiagnostics::class, new MailTransportDiagnostics(
            static fn (string $domain): bool => in_array($domain, $resolving, true)
        ));
    }

    public function test_resend_without_api_key_is_misconfigured_not_skipped(): void
    {
        // Cœur du correctif : avant #9317 ce cas renvoyait `skipped` + `ok`.
        config([
            'mail.default' => 'resend',
            'services.resend.key' => '',
            'resend.api_key' => '',
            'queue.default' => 'sync',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200);
        $response->assertJsonPath('checks.mail.ok', false);
        $response->assertJsonPath('checks.mail.status', 'misconfigured');
        $response->assertJsonPath('checks.mail.error', 'RESEND_KEY_MISSING');
        $response->assertJsonPath('degraded_checks', ['mail']);
        // Non critique : l'API reste servable sans mail.
        $response->assertJsonPath('failed_checks', []);
    }

    public function test_resend_with_unresolvable_from_domain_is_reported(): void
    {
        // Le mode de panne mesuré : clé présente, mais l'expéditeur pointe sur
        // un domaine NXDOMAIN (`leopardo-rh.com`, cf. #8062) — Resend refuse
        // tout envoi depuis un domaine non vérifié.
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'noreply@leopardo-rh.com',
            'queue.default' => 'sync',
        ]);
        $this->fakeDns([]);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertJsonPath('checks.mail.ok', false);
        $response->assertJsonPath('checks.mail.status', 'unverified_domain');
        $response->assertJsonPath('checks.mail.error', 'MAIL_FROM_DOMAIN_UNRESOLVED');
        $response->assertJsonPath('degraded_checks', ['mail']);
    }

    public function test_resend_with_verified_domain_is_configured(): void
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'noreply@hurkonsan.com',
            'queue.default' => 'sync',
        ]);
        $this->fakeDns(['hurkonsan.com']);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertJsonPath('checks.mail.ok', true);
        $response->assertJsonPath('checks.mail.status', 'configured');
        $response->assertJsonPath('degraded_checks', []);
    }

    public function test_resend_key_is_read_from_the_package_config_too(): void
    {
        // `resend/resend-laravel` lit `resend.api_key` en priorité puis
        // `services.resend.key` : une clé posée au bon endroit ne doit pas
        // être rapportée absente.
        config([
            'mail.default' => 'resend',
            'services.resend.key' => '',
            'resend.api_key' => 're_from_package_config',
            'mail.from.address' => 'noreply@hurkonsan.com',
            'queue.default' => 'sync',
        ]);
        $this->fakeDns(['hurkonsan.com']);

        $this->getJson('/api/v1/health/ready')
            ->assertJsonPath('checks.mail.status', 'configured');
    }

    public function test_local_mailers_stay_skipped(): void
    {
        // `log`/`array` n'ont aucune dépendance externe : `skipped` reste
        // l'état honnête (comportement #7385 préservé).
        config(['mail.default' => 'log', 'queue.default' => 'sync']);

        $this->getJson('/api/v1/health/ready')
            ->assertJsonPath('checks.mail.status', 'skipped')
            ->assertJsonPath('checks.mail.ok', true)
            ->assertJsonPath('degraded_checks', []);
    }

    public function test_unknown_network_transport_is_not_declared_healthy_by_default(): void
    {
        // Un transport réseau hors périmètre de sonde est DIT (`unprobed`)
        // plutôt que déclaré sain par défaut — c'est la leçon de #9317.
        config(['mail.default' => 'postmark', 'queue.default' => 'sync']);

        $this->getJson('/api/v1/health/ready')
            ->assertJsonPath('checks.mail.status', 'unprobed')
            ->assertJsonPath('checks.mail.ok', true);
    }

    public function test_smtp_probe_is_unchanged(): void
    {
        // Non-régression #7385 : le `tcp connect` SMTP reste le comportement
        // du transport smtp (port fermé → `unreachable`, jamais `skipped`).
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'queue.default' => 'sync',
        ]);

        $this->getJson('/api/v1/health/ready')
            ->assertJsonPath('checks.mail.ok', false)
            ->assertJsonPath('checks.mail.status', 'unreachable');
    }
}
