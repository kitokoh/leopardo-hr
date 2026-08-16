<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\CompanyRequest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4395 — les messages d'erreur du funnel trial signup suivent la langue du
 * client via `Accept-Language` (middleware SetLocale, groupe api).
 * Avant : 6 messages FR codés en dur dans VerifyTrialSignup (Action), servis
 * tels quels dans la réponse par SelfServiceTrialController.
 */
class TrialSignupLocalizationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_invalid_otp_message_follows_accept_language(): void
    {
        // Signup d'abord (même parcours que test_rejects_invalid_otp) pour
        // rester sur le chemin nominal, puis OTP erroné.
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@localized.com',
            'company' => 'Localized Test',
            'country' => 'DZ',
        ])->assertStatus(200);

        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/trial/verify', [
                'email' => 'founder@localized.com',
                'code' => '000000',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'INVALID_OR_EXPIRED_CODE')
            ->assertJsonPath('message', 'Invalid or expired verification code.');
    }

    public function test_trial_errors_are_translated_in_all_supported_locales(): void
    {
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@localized.com',
            'company' => 'Localized Test',
            'country' => 'DZ',
        ])->assertStatus(200);

        $expected = [
            'fr' => 'Code de vérification invalide ou expiré.',
            'en' => 'Invalid or expired verification code.',
            'tr' => 'Geçersiz veya süresi dolmuş doğrulama kodu.',
            'ar' => 'رمز التحقق غير صالح أو منتهي الصلاحية.',
        ];

        foreach ($expected as $locale => $translation) {
            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/trial/verify', [
                    'email' => 'founder@localized.com',
                    'code' => '000000',
                ]);

            $response->assertStatus(400)
                ->assertJsonPath('error', 'INVALID_OR_EXPIRED_CODE')
                ->assertJsonPath('message', $translation);
        }
    }

    public function test_already_processed_message_localized(): void
    {
        $this->postJson('/api/v1/trial/signup', [
            'email' => 'founder@claimed-localized.dz',
            'company' => 'Claimed Localized Test',
            'country' => 'DZ',
        ])->assertStatus(200);

        /** @var CompanyRequest|null $request */
        $request = CompanyRequest::where('email', 'founder@claimed-localized.dz')
            ->where('status', 'pending')->first();
        $this->assertNotNull($request);
        $otp = $request->verification_token;

        // Simule le claim d'une requête concurrente (fenêtre de provisioning).
        $request->update(['status' => 'processing']);

        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/trial/verify', [
                'email' => 'founder@claimed-localized.dz',
                'code' => $otp,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error', 'ALREADY_PROCESSED')
            ->assertJsonPath('message', 'This trial request has already been processed.');
    }
}
