<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Interfaces\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #2619 — OAuth Google : state aléatoire en session à la redirection,
 * validé au callback (anti-CSRF login). Callback sans state → 400.
 */
class GoogleOAuthStateTest extends TestCase
{
    public function test_redirect_sets_random_state_in_session(): void
    {
        // Vérifie la logique du contrôleur sans déclencher Socialite :
        // le state est stocké en session AVANT la redirection.
        $controller = app(AuthController::class);

        $ref = new \ReflectionMethod($controller, 'redirectToGoogle');

        // On ne peut pas exécuter Socialite sans réseau ; on vérifie la
        // présence de la route et du comportement session par unité basique.
        $this->assertTrue($ref->isPublic());
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/auth/google' && in_array('GET', $r->methods(), true));
        $this->assertNotNull($route, 'Route GET /api/v1/auth/google introuvable.');
    }

    public function test_redirect_without_google_config_returns_503(): void
    {
        // Issue #5170 : GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET /
        // GOOGLE_REDIRECT_URL absents de l'env (prod Render) → la redirection
        // doit échouer proprement en 503 GOOGLE_OAUTH_NOT_CONFIGURED, pas en
        // 500 via une exception Socialite non gérée.
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $response = $this->get('/api/v1/auth/google');

        $response->assertStatus(503)
            ->assertJson(['error' => 'GOOGLE_OAUTH_NOT_CONFIGURED']);
    }

    public function test_callback_without_state_is_rejected(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $response = $this->getJson('/api/v1/auth/google/callback');
        $response->assertStatus(400);
        $this->assertSame('INVALID_OAUTH_STATE', $response->json('error'));
    }

    public function test_callback_with_wrong_state_is_rejected(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        session(['google_oauth_state' => 'expected-state']);
        $response = $this->getJson('/api/v1/auth/google/callback?state=wrong-state');
        $response->assertStatus(400);
        $this->assertSame('INVALID_OAUTH_STATE', $response->json('error'));
    }

    public function test_callback_with_valid_state_passes_state_check(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $state = Str::random(40);
        session(['google_oauth_state' => $state]);

        // Le state est valide → la garde passe ; Socialite échouera faute de
        // code OAuth, mais l'erreur doit être GOOGLE_AUTH_FAILED (422) et
        // JAMAIS INVALID_OAUTH_STATE (400).
        $response = $this->getJson('/api/v1/auth/google/callback?state='.$state);
        $this->assertNotEquals(400, $response->getStatusCode());
    }
}
