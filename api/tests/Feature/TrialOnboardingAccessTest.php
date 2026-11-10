<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProvisionDemoTenantJob;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9305 / #9306 — verrous du parcours d'ouverture d'un espace d'essai.
 *
 * Deux régressions silencieuses étaient possibles, et l'une des deux était
 * déjà en production :
 *
 *  - #9305 : `POST /trial/signup` en `self_service` répondait 503
 *    `TRIAL_OTP_SEND_FAILED` quand le transport e-mail était indisponible.
 *    Le repli vers le parcours guidé n'existait que dans la route Next
 *    `/api/forms/signup` : tout client direct restait bloqué, et le contrat
 *    d'API dépendait d'un détail d'implémentation du front.
 *  - #9306 : une fois le repli guidé appliqué, le tenant atteignait `ready`
 *    avec `password_set = false` et `access_sent = false` — un espace
 *    provisionné mais inaccessible, sans aucun moyen de connexion exposé.
 *
 * Ces tests verrouillent l'invariant produit : **un responsable qui s'inscrit
 * obtient toujours un espace ET un chemin de connexion**, quel que soit
 * l'état du mailer.
 */
class TrialOnboardingAccessTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * #9305 — une panne d'envoi OTP ne doit pas se traduire par un 503 : le
     * serveur bascule sur le parcours guidé et provisionne quand même.
     */
    public function test_self_service_signup_falls_back_to_guided_when_otp_cannot_be_sent(): void
    {
        Queue::fake();

        // Le mailer est la seule dépendance en panne : on simule un envoi
        // impossible au niveau de l'action (c'est exactement ce que le
        // contrôleur reçoit en production quand la queue `emails` est KO).
        $this->mock(RequestTrialSignup::class, function ($mock): void {
            $mock->shouldReceive('execute')->andReturn(false);
            $mock->shouldReceive('findExistingManager')->andReturn(null);
            $mock->shouldReceive('resolveCompanyName')->andReturn('Le Bistrot QA');
        });

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'restaurateur@demo.com',
            'company' => 'Le Bistrot QA',
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'country' => 'FR',
            'locale' => 'fr',
            'plan' => 'pilot',
            'solutions' => ['restaurant'],
            'requestedWorkflow' => 'self_service',
        ]);

        // Aucun 503 : on ne perd pas le prospect parce qu'un e-mail ne part pas.
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.workflow', 'guided_trial')
            ->assertJsonPath('data.otp_sent', false)
            ->assertJsonPath('data.status', 'provisioning_sandbox')
            ->assertJsonStructure(['data' => ['provisioning_token']]);

        $token = $response->json('data.provisioning_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        // L'espace est réellement en cours de provisioning.
        $this->assertDatabaseHas('trial_provisionings', [
            'email' => 'restaurateur@demo.com',
            'provisioning_token' => $token,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProvisionDemoTenantJob::class);
    }

    /**
     * #9306 — tant que le manager n'a pas choisi son mot de passe, le statut
     * doit exposer un lien d'activation : sans lui, un espace `ready` reste
     * inaccessible quand l'e-mail d'accès n'est jamais parti.
     */
    public function test_ready_trial_without_password_exposes_activation_url(): void
    {
        $token = str_repeat('c', 64);

        DB::table('trial_provisionings')->insert([
            'email' => 'restaurateur@demo.com',
            'provisioning_token' => $token,
            'status' => 'ready',
            'login_url' => '/auth/login',
            'provisioned_at' => '2026-11-10 06:47:19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/trial/status?token='.$token);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.password_set', false)
            ->assertJsonPath('data.access_sent', false);

        // Le lien d'activation pointe sur l'écran qui consomme le même token
        // que `POST /trial/set-password`.
        $activationUrl = $response->json('data.activation_url');
        $this->assertIsString($activationUrl);
        $this->assertStringEndsWith('/auth/activate/'.$token, $activationUrl);
    }

    /**
     * #9306 — le lien disparaît dès que le mot de passe est défini : on ne
     * laisse pas traîner un chemin d'accès inutile (et le token est à usage
     * unique côté `SetTrialPassword`).
     */
    public function test_ready_trial_with_password_does_not_expose_activation_url(): void
    {
        $token = str_repeat('d', 64);

        DB::table('trial_provisionings')->insert([
            'email' => 'restaurateur@demo.com',
            'provisioning_token' => $token,
            'status' => 'ready',
            'login_url' => '/auth/login',
            'provisioned_at' => '2026-11-10 06:47:19',
            'password_set_at' => '2026-11-10 07:02:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/trial/status?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.password_set', true)
            ->assertJsonMissingPath('data.activation_url');
    }
}
