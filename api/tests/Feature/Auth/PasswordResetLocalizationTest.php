<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4191 — les messages API (`localized_message`) suivent la langue du
 * client via `Accept-Language` (middleware SetLocale, groupe api).
 * Avant : chaînes FR codées en dur dans PasswordResetController (et ~20
 * autres contrôleurs — batch 1 : auth).
 */
class PasswordResetLocalizationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_forgot_password_localized_message_follows_accept_language(): void
    {
        // Email inconnu → réponse générique anti-énumération, mais le message
        // localisé doit suivre Accept-Language (SetLocale middleware).
        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('localized_message', 'If an account exists for this email, a reset link has been sent.');
    }

    public function test_forgot_password_defaults_to_french_without_accept_language(): void
    {
        // Le client de test envoie `Accept-Language: en-us,en;q=0.5` par défaut
        // (headers PHP) — on vide explicitement le header pour exercer le vrai
        // chemin « sans préférence » (fallback Language::DEFAULT = fr).
        $response = $this->withHeader('Accept-Language', '')
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk()
            ->assertJsonPath('localized_message', "Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.");
    }

    public function test_forgot_password_message_key_is_translated_in_all_supported_locales(): void
    {
        $expected = [
            'fr' => "Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.",
            'en' => 'If an account exists for this email, a reset link has been sent.',
            'tr' => 'Bu e-posta icin bir hesap varsa, bir sifirlama baglantisi gonderildi.',
            'ar' => 'إذا كان هناك حساب لهذا البريد الإلكتروني، فقد تم إرسال رابط إعادة التعيين.',
        ];

        foreach ($expected as $locale => $translation) {
            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

            $response->assertOk()
                ->assertJsonPath('localized_message', $translation);
        }
    }
}
