<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Security\InsecureConfigurationGuard;
use RuntimeException;
use Tests\TestCase;

/**
 * #9349 (F7, CORS) / #9352 (F10, en-têtes) / #9353 (F11, mot de passe DB)
 * — gardes de configuration.
 *
 * Ces tests ne vérifient pas un flux métier mais un INVARIANT DE
 * DÉPLOIEMENT : une configuration permissive ne doit pas pouvoir passer
 * silencieusement en production.
 */
class SecurityConfigurationTest extends TestCase
{
    // ── F11 — mot de passe base de données ────────────────────────────

    public function test_production_rejects_an_empty_database_password(): void
    {
        $this->expectException(RuntimeException::class);
        InsecureConfigurationGuard::check('production', '');
    }

    public function test_production_rejects_a_known_development_password(): void
    {
        $this->expectException(RuntimeException::class);
        InsecureConfigurationGuard::check('production', 'leopardo-dev-local-only');
    }

    public function test_production_accepts_a_real_secret(): void
    {
        InsecureConfigurationGuard::check('production', 'a-real-generated-secret-value');

        $this->addToAssertionCount(1);
    }

    public function test_local_and_testing_are_exempt(): void
    {
        // Onboarding : `docker compose` utilise le mot de passe de dev par
        // construction. Le garde ne doit pas casser le démarrage local.
        InsecureConfigurationGuard::check('local', 'leopardo-dev-local-only');
        InsecureConfigurationGuard::check('testing', '');

        $this->addToAssertionCount(1);
    }

    public function test_env_example_ships_only_a_guarded_development_password(): void
    {
        // #9353 puis 86fffeb9 : la valeur de developpement a ete RESTAUREE
        // dans `.env.example` parce que l'onboarding documente
        // (`cp api/.env.example api/.env` + `docker compose up`) en depend —
        // `php artisan serve` ne transmet pas l'environnement du conteneur au
        // processus enfant, donc un `DB_PASSWORD` vide casse le health check
        // local (503).
        //
        // L'invariant n'est donc plus « le fichier ne contient aucun mot de
        // passe » mais « le mot de passe qu'il contient est REFUSE hors
        // local/testing » : c'est `InsecureConfigurationGuard` qui protege la
        // production, et c'est cela que ce test doit verrouiller.
        $path = base_path('.env.example');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/^DB_PASSWORD=(.*)$/m',
            $contents,
            'DB_PASSWORD doit rester declare dans .env.example (onboarding).',
        );

        preg_match('/^DB_PASSWORD=(.*)$/m', $contents, $matches);
        $shipped = trim($matches[1] ?? '');

        // La valeur livree ne doit JAMAIS etre acceptee en production.
        $this->expectException(RuntimeException::class);
        InsecureConfigurationGuard::check('production', $shipped);
    }

    // ── F7 — CORS ─────────────────────────────────────────────────────

    public function test_cors_methods_are_restricted(): void
    {
        $methods = config('cors.allowed_methods');

        $this->assertIsArray($methods);
        $this->assertNotContains('*', $methods);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertNotContains('TRACE', $methods);
    }

    public function test_cors_env_origin_rejects_a_wildcard(): void
    {
        putenv('CORS_EXTRA_ORIGIN=*');

        $this->assertNull(cors_env_origin('CORS_EXTRA_ORIGIN'));

        putenv('CORS_EXTRA_ORIGIN');
    }

    public function test_cors_env_origin_rejects_a_value_with_a_path(): void
    {
        putenv('CORS_EXTRA_ORIGIN=https://app.example.com/dashboard');

        $this->assertNull(cors_env_origin('CORS_EXTRA_ORIGIN'));

        putenv('CORS_EXTRA_ORIGIN');
    }

    public function test_cors_env_origin_normalises_a_valid_origin(): void
    {
        putenv('CORS_EXTRA_ORIGIN=https://app.example.com/');

        $this->assertSame('https://app.example.com', cors_env_origin('CORS_EXTRA_ORIGIN'));

        putenv('CORS_EXTRA_ORIGIN');
    }

    // ── F3 — sessions ─────────────────────────────────────────────────

    public function test_sessions_are_encrypted_by_default(): void
    {
        // #9345 — la charge de session porte l'identité et le contexte
        // tenant : elle ne doit pas atterrir en clair sur le disque.
        $this->assertTrue((bool) config('session.encrypt'));
    }

    public function test_session_cookie_is_secure_by_default(): void
    {
        // #9345 — `env('SESSION_SECURE_COOKIE')` sans défaut laissait partir
        // le cookie sans l'attribut `Secure` sur un environnement incomplet.
        $this->assertTrue((bool) config('session.secure'));
    }

    // ── F10 — en-têtes de sécurité ────────────────────────────────────

    public function test_api_responses_carry_the_cross_origin_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        // Route publique : si elle n'existe pas dans cet environnement, on
        // retombe sur le rendu d'erreur de l'API, qui passe aussi par le
        // middleware.
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }
}
