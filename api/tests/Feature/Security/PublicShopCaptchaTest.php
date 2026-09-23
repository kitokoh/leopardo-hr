<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #8054 — anti-bot réel sur les boutiques publiques (Travel +
 * Restaurant).
 *
 * Avant : seul le caractère NON VIDE du header `X-Captcha-Token` était
 * exigé quand `*.public_shop.captcha_secret` était configuré — le secret
 * n'était jamais utilisé (`curl -H "X-Captcha-Token: x"` bypassait tout).
 *
 * Après : le jeton est vérifié côté serveur (siteverify, timeout court,
 * FAIL-CLOSED) via le CaptchaVerifier partagé. La suite vérifie le contrat
 * des deux verticales sans atteindre la base : le gate anti-bot précède la
 * résolution du jeton boutique → 403 (captcha KO) vs 401 (captcha OK mais
 * jeton boutique absent).
 */
class PublicShopCaptchaTest extends TestCase
{
    private const VERIFY_URL = 'https://captcha.test/siteverify';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('travel.public_shop.captcha_secret', 'travel-secret-8054');
        config()->set('travel.public_shop.captcha_verify_url', self::VERIFY_URL);
        config()->set('restaurantmanager.public_shop.captcha_secret', 'resto-secret-8054');
        config()->set('restaurantmanager.public_shop.captcha_verify_url', self::VERIFY_URL);
    }

    // ─── Travel ────────────────────────────────────────────────────────

    public function test_travel_rejects_missing_captcha_token(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => true], 200)]);

        $this->getJson('/api/v1/public/travel/shop/trips')
            ->assertStatus(403);

        // Aucun appel fournisseur sans jeton client (court-circuit local).
        Http::assertNothingSent();
    }

    public function test_travel_rejects_arbitrary_token_the_old_bypass_is_dead(): void
    {
        // Reproduction du bypass historique : `curl -H "X-Captcha-Token: x"`.
        // Le fournisseur refuse ce jeton → 403 (avant #8054 : la requête
        // passait le gate sans AUCUN appel serveur).
        Http::fake([self::VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200)]);

        $this->getJson('/api/v1/public/travel/shop/trips', ['X-Captcha-Token' => 'x'])
            ->assertStatus(403);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), self::VERIFY_URL)
            && str_contains((string) $request->body(), 'travel-secret-8054'));
    }

    public function test_travel_accepts_provider_validated_token(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => true], 200)]);

        // Captcha validé → le gate suivant répond (jeton boutique manquant).
        $this->getJson('/api/v1/public/travel/shop/trips', ['X-Captcha-Token' => 'real-token'])
            ->assertStatus(401);
    }

    public function test_travel_fails_closed_when_provider_unreachable(): void
    {
        // Un fournisseur en panne ne désactive JAMAIS la protection.
        Http::fake([self::VERIFY_URL => Http::response('upstream down', 503)]);

        $this->getJson('/api/v1/public/travel/shop/trips', ['X-Captcha-Token' => 'real-token'])
            ->assertStatus(403);
    }

    // ─── Restaurant ────────────────────────────────────────────────────

    public function test_restaurant_rejects_arbitrary_token(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => false], 200)]);

        $this->getJson('/api/v1/public/restaurant/shop/menu', ['X-Captcha-Token' => 'x'])
            ->assertStatus(403);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->body(), 'resto-secret-8054'));
    }

    public function test_restaurant_accepts_provider_validated_token(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => true], 200)]);

        $this->getJson('/api/v1/public/restaurant/shop/menu', ['X-Captcha-Token' => 'real-token'])
            ->assertStatus(401);
    }

    // ─── Sans secret configuré : hook inactif (comportement inchangé) ──

    public function test_no_secret_configured_means_no_captcha_gate(): void
    {
        config()->set('travel.public_shop.captcha_secret', null);

        Http::fake([self::VERIFY_URL => Http::response(['success' => false], 200)]);

        // Pas de gate captcha → on atteint directement le contrôle du jeton
        // boutique (401), et AUCUN appel sortant n'est émis.
        $this->getJson('/api/v1/public/travel/shop/trips')
            ->assertStatus(401);

        Http::assertNothingSent();
    }
}
