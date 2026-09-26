<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8164 (suite #8144) — les logs d'AuthService ne doivent JAMAIS porter
 * l'email de connexion en clair (PII) : les événements de résolution employé
 * / tenant orphelin sont journalisés avec un identifiant haché
 * (`email_hash`, sha256 tronqué — corrélation possible, donnée non exposée).
 *
 * Avant ce correctif, 5 points de log du fichier journalisaient
 * `['email' => $email]` (résolution employé échouée ×3, tenant orphelin ×2).
 *
 * Déclencheur : le scénario PROD réel du log `auth.login_employee_resolution_failed`
 * est une infrastructure dégradée (tenant partiellement migré). Pour le
 * reproduire dans la fixture MVP (toutes les tables en `public`), la table
 * `public.companies` est temporairement renommée : le balayage des schémas
 * tenants (#6563) lève alors la QueryException 42P01 convertie en 401 — c'est
 * EXACTEMENT le chemin qui journalisait l'email en clair avant #8164. La
 * table est restaurée en `finally` (fixture partagée au sein du processus).
 *
 * Capture : `Log::spy()` avec `channel()` mappé sur le spy lui-même — même
 * pattern que TrialSignupLogsPiiTest (#8144), car certains chemins du service
 * journalisent via `Log::channel('structured')`.
 */
class AuthServiceLogsPiiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_resolution_failure_log_never_contains_the_raw_email(): void
    {
        $email = 'Ghost.PII@Orphan.Test';

        // NB : la résolution user_lookups est en correspondance EXACTE — la
        // ligne est insérée avec la même casse que celle du login.
        DB::table('user_lookups')->insert([
            'email' => $email,
            'company_id' => '99999999-9999-9999-9999-999999999999',
            'employee_id' => 999999,
            'schema_name' => 'schema_inexistant_xyz',
            'role' => 'employee',
        ]);

        $log = Log::spy();
        $log->shouldReceive('channel')->andReturnSelf();

        // Infrastructure dégradée : `public.companies` injoignable le temps du
        // login → QueryException 42P01 → catch #2652 → log + 401.
        DB::statement('ALTER TABLE public.companies RENAME TO companies_pii_bak');

        try {
            app(AuthService::class)->login($email, 'password123', 'unit-tests');
            $this->fail('Une résolution en échec doit produire InvalidCredentialsException.');
        } catch (InvalidCredentialsException) {
            // contrat attendu (#2652) — ce test porte sur le CONTENU du log.
        } finally {
            DB::statement('ALTER TABLE public.companies_pii_bak RENAME TO companies');
            DB::table('user_lookups')->where('email', $email)->delete();
        }

        $this->assertInstanceOf(MockInterface::class, $log);

        // Aucun log (message OU contexte) ne porte l'email en clair, quelle
        // que soit la casse saisie par l'utilisateur.
        $log->shouldHaveReceived('warning')->withArgs(function (mixed $message, array $context = []) use ($email): bool {
            $this->assertStringNotContainsString($email, (string) $message);
            $this->assertStringNotContainsString(mb_strtolower($email), (string) json_encode($context));

            return true;
        });

        // …et le pseudonyme haché est bien journalisé (corrélation possible) —
        // même convention de hachage tronqué que #8144.
        $expectedHash = substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
        $log->shouldHaveReceived('warning')
            ->with('auth.login_employee_resolution_failed', \Mockery::on(
                fn (array $context): bool => ($context['email_hash'] ?? null) === $expectedHash
                    && ! array_key_exists('email', $context)
            ));
    }
}
