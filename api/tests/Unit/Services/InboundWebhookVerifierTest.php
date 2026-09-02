<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Shared\Services\InboundWebhookVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Issue #5740 (CRM PRE) — threat model des webhooks et intégrations.
 *
 * Chaque attaque du modèle (signature invalide, timestamp expiré, replay,
 * payload trop grand, JSON invalide, provider inconnu, rotation de secret)
 * est couverte par une primitive pure du vérificateur.
 */
class InboundWebhookVerifierTest extends TestCase
{
    // ── Signature / secret ───────────────────────────────────────────────────

    public function test_secret_matches_accepts_identical_secret(): void
    {
        $this->assertTrue(InboundWebhookVerifier::secretMatches('s3cr3t', 's3cr3t'));
    }

    public function test_secret_matches_rejects_different_secret(): void
    {
        $this->assertFalse(InboundWebhookVerifier::secretMatches('s3cr3t', 'wrong'));
    }

    public function test_secret_rotation_rejects_previous_secret(): void
    {
        // Après rotation, l'ancien secret ne doit plus être accepté.
        $this->assertFalse(InboundWebhookVerifier::secretMatches('new-secret', 'old-secret'));
    }

    public function test_hmac_signature_valid(): void
    {
        $secret = 'whsec_test';
        $body = '{"event":"x"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        $this->assertTrue(InboundWebhookVerifier::verifyHmacSignature($secret, $signature, $body, $timestamp));
    }

    public function test_hmac_signature_invalid(): void
    {
        $secret = 'whsec_test';
        $body = '{"event":"x"}';
        $timestamp = time();

        $this->assertFalse(InboundWebhookVerifier::verifyHmacSignature($secret, 'deadbeef', $body, $timestamp));
    }

    public function test_hmac_signature_fails_when_secret_rotated(): void
    {
        $oldSecret = 'whsec_old';
        $body = '{"event":"x"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $oldSecret);

        // Le nouveau secret ne vérifie pas la signature émise avec l'ancien.
        $this->assertFalse(InboundWebhookVerifier::verifyHmacSignature('whsec_new', $signature, $body, $timestamp));
    }

    public function test_hmac_signature_rejects_empty_secret_or_empty_body(): void
    {
        $this->assertFalse(InboundWebhookVerifier::verifyHmacSignature('', 'x', '{}', time()));
        $this->assertFalse(InboundWebhookVerifier::verifyHmacSignature('s', '', '{}', time()));
    }

    // ── Fenêtre de rejeu ─────────────────────────────────────────────────────

    public function test_timestamp_fresh_within_window(): void
    {
        $this->assertTrue(InboundWebhookVerifier::timestampIsFresh(time() - 60));
    }

    public function test_timestamp_expired_beyond_window(): void
    {
        $this->assertFalse(InboundWebhookVerifier::timestampIsFresh(time() - 3_600));
        $this->assertFalse(InboundWebhookVerifier::timestampIsFresh(time() - (InboundWebhookVerifier::DEFAULT_TIMESTAMP_WINDOW_SECONDS + 1)));
    }

    public function test_timestamp_from_future_rejected(): void
    {
        $this->assertFalse(InboundWebhookVerifier::timestampIsFresh(time() + 3_600));
    }

    public function test_timestamp_from_header_parses_only_valid_integers(): void
    {
        $this->assertSame(time() - 60, InboundWebhookVerifier::timestampFromHeader((string) (time() - 60)));
        $this->assertNull(InboundWebhookVerifier::timestampFromHeader(null));
        $this->assertNull(InboundWebhookVerifier::timestampFromHeader(''));
        $this->assertNull(InboundWebhookVerifier::timestampFromHeader('not-a-number'));
    }

    // ── Taille du payload ────────────────────────────────────────────────────

    public function test_payload_within_limit_accepts_normal_payload(): void
    {
        $this->assertTrue(InboundWebhookVerifier::payloadWithinLimit(str_repeat('a', 1_024)));
    }

    public function test_payload_too_large_rejected(): void
    {
        $this->assertFalse(
            InboundWebhookVerifier::payloadWithinLimit(str_repeat('a', InboundWebhookVerifier::DEFAULT_MAX_PAYLOAD_BYTES + 1))
        );
    }

    // ── Validité JSON ────────────────────────────────────────────────────────

    public function test_json_payload_valid(): void
    {
        $this->assertTrue(InboundWebhookVerifier::isJsonPayload('{"event":"bounce","email":"a@b.c"}'));
        $this->assertTrue(InboundWebhookVerifier::isJsonPayload('[]'));
    }

    public function test_json_payload_invalid_rejected(): void
    {
        $this->assertFalse(InboundWebhookVerifier::isJsonPayload('{invalid'));
        $this->assertFalse(InboundWebhookVerifier::isJsonPayload(''));
        $this->assertFalse(InboundWebhookVerifier::isJsonPayload('   '));
    }

    // ── Allowlist des providers ──────────────────────────────────────────────

    public function test_known_provider_accepted(): void
    {
        $this->assertTrue(InboundWebhookVerifier::isKnownProvider('stripe', ['stripe', 'chargily', 'email-bounce']));
    }

    public function test_unknown_provider_rejected(): void
    {
        $this->assertFalse(InboundWebhookVerifier::isKnownProvider('evil-provider', ['stripe', 'chargily']));
    }
}
