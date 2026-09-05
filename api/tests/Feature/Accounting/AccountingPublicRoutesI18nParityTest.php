<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6676 — les routes PUBLIQUES du module Accounting
 * (/accounting/documents/shared/{token}, /accounting/payment-webhooks/{gateway})
 * doivent localiser leur message d'erreur via Accept-Language, comme le reste
 * de l'API (contraste : /onboarding/invitation/{token} → « Invitation
 * introuvable. » en fr). En prod, ces routes répondaient en anglais même avec
 * `Accept-Language: fr`.
 */
class AccountingPublicRoutesI18nParityTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_shared_document_error_is_localized_with_accept_language_fr(): void
    {
        $this->withHeader('Accept-Language', 'fr')
            ->getJson('/api/v1/accounting/documents/shared/unknown-token')
            ->assertStatus(404)
            ->assertJsonPath('error', 'DOCUMENT_SHARE_NOT_FOUND')
            ->assertJsonPath('localized_message', 'Lien de partage introuvable ou expiré.');
    }

    public function test_shared_document_error_is_localized_with_accept_language_en(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/accounting/documents/shared/unknown-token')
            ->assertStatus(404)
            ->assertJsonPath('error', 'DOCUMENT_SHARE_NOT_FOUND')
            ->assertJsonPath('localized_message', 'Share link not found or expired.');
    }

    public function test_payment_webhook_unknown_gateway_is_localized_fr(): void
    {
        // Le webhook valide la signature HMAC d'abord (fail-closed, 401
        // WEBHOOK_SIGNATURE_INVALID) — la passerelle inconnue est rejetée par
        // le service. L'essentiel ici : le message est LOCALISÉ en français
        // (#6676), pas un anglais par défaut.
        $this->withHeader('Accept-Language', 'fr')
            ->postJson('/api/v1/accounting/payment-webhooks/unknown-gateway', [])
            ->assertStatus(401)
            ->assertJsonPath('localized_message', 'Signature de webhook invalide ou absente.');
    }
}
