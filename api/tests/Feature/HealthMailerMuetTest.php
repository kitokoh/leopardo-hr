<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * #7385 — « mailer muet » : le trou que #7255 avait identifie sans le combler.
 *
 * `/health/ready` couvrait la base, Redis et la queue, mais PAS le transport
 * mail. Or une instance dont le SMTP ne repond plus renvoie `status: ok`
 * alors que la reinitialisation de mot de passe est deja morte : le
 * controleur avale l'echec d'envoi par anti-enumeration (cf. #6751), donc
 * l'utilisateur lit « un lien vient d'etre envoye » et ne recoit jamais rien.
 *
 * Constat live du 2026-09-14 : `POST /trial/signup` renvoyait 503
 * `TRIAL_OTP_SEND_FAILED` (transport HS) pendant que
 * `POST /auth/forgot-password` renvoyait 200 `PASSWORD_RESET_SENT` et que
 * `/health/ready` repondait `status: ok` — sans aucune mention du mail.
 *
 * Le check est volontairement NON critique : sans SMTP l'API reste servable
 * (connexion, donnees, pointage), donc pas de 503 et pas de redemarrage
 * d'instance saine. Le signal vit dans `checks.mail` et `degraded_checks`.
 */
class HealthMailerMuetTest extends TestCase
{
    public function test_ready_exposes_a_mail_check(): void
    {
        config(['queue.default' => 'sync']);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200);
        $response->assertJsonPath('checks.mail.ok', true);
        $response->assertJsonStructure(['checks' => ['mail']]);
    }

    public function test_local_mailer_is_skipped_not_failed(): void
    {
        // `log` / `array` n'ont aucune dependance externe : rien a sonder.
        config(['mail.default' => 'log', 'queue.default' => 'sync']);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertJsonPath('checks.mail.status', 'skipped');
        $response->assertJsonPath('degraded_checks', []);
        $response->assertJsonPath('status', 'ok');
    }

    public function test_unreachable_smtp_is_reported_as_degraded_without_503(): void
    {
        config([
            'mail.default' => 'smtp',
            // Port ferme : le `tcp connect` echoue immediatement.
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'queue.default' => 'sync',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        // Coeur du correctif : le mailer muet n'est plus invisible.
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('checks.mail.ok', false);
        $response->assertJsonPath('checks.mail.status', 'unreachable');
        $response->assertJsonPath('degraded_checks', ['mail']);

        // Et il ne declenche PAS de 503 : l'API reste servable sans mail.
        $response->assertJsonPath('failed_checks', []);
    }

    public function test_missing_smtp_host_is_misconfiguration(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '',
            'queue.default' => 'sync',
        ]);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertJsonPath('checks.mail.status', 'misconfigured');
        $response->assertJsonPath('checks.mail.error', 'MAIL_HOST_MISSING');
        $response->assertJsonPath('degraded_checks', ['mail']);
    }

    public function test_health_matrix_also_exposes_mail(): void
    {
        config(['queue.default' => 'sync']);

        $response = $this->getJson('/api/v1/health');

        $response->assertJsonStructure(['checks' => ['mail']]);
    }
}
