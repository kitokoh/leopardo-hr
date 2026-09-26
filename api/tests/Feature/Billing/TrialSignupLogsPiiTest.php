<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-002 (#8144) — les logs applicatifs du parcours trial ne doivent JAMAIS
 * porter l'email du prospect en clair (PII) : les événements sont journalisés
 * avec un identifiant haché (`email_hash`), qui permet de corréler sans
 * exposer la donnée.
 *
 * Avant ce correctif, la route de signup journalisait `['email' => $email]`
 * sur plusieurs chemins (dont le doublon d'email et la course de provisioning).
 *
 * Capture : handler Monolog `TestHandler` posé sur le logger réel (un
 * `Log::spy()` casserait `Log::channel(...)` utilisé par la route).
 */
class TrialSignupLogsPiiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_trial_signup_logs_never_contain_the_raw_email(): void
    {
        $email = 'fondateur.pii@exemple.dz';

        // Un manager EXISTANT porte l'email : c'est ce cas qui déclenche le
        // chemin « doublon d'email » journalisé (L157 avant #8144).
        /** @var Company $company */
        $company = Company::factory()->create();
        Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => $email,
        ]);

        // 1er signup : crée la demande.
        $this->postJson('/api/v1/trial/signup', [
            'email' => $email,
            'company' => 'PII Trial Co',
            'country' => 'DZ',
        ])->assertStatus(200);

        // Capture des logs du 2e appel (chemin « doublon d'email » — c'est CE
        // chemin qui journalisait l'email en clair). `channel()` est mappé sur
        // le spy lui-même : sans cela, `Log::channel('structured')` renverrait
        // null et casserait la requête.
        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();

        $this->postJson('/api/v1/trial/signup', [
            'email' => $email,
            'company' => 'PII Trial Co',
            'country' => 'DZ',
        ])->assertStatus(200);

        $this->assertInstanceOf(MockInterface::class, $log);

        // Aucun log (message OU contexte) ne porte l'email en clair.
        $log->shouldHaveReceived('info')->withArgs(function (mixed $message, array $context = []) use ($email): bool {
            $this->assertStringNotContainsString($email, (string) $message);
            $this->assertStringNotContainsString($email, (string) json_encode($context));

            return true;
        });

        // …et le pseudonyme est bien journalisé (corrélation possible, valeur
        // attendue calculée ici : même schéma de hachage tronqué).
        $expectedHash = substr(hash('sha256', mb_strtolower($email)), 0, 16);
        $log->shouldHaveReceived('info')
            ->with('trial.signup_duplicate_email_uniform_response', ['email_hash' => $expectedHash]);
    }
}
