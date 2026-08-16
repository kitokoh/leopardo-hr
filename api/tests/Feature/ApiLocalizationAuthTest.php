<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4191 : les messages API doivent être localisés via les catalogues
 * lang/{fr,en,tr,ar} (Accept-Language) et non codés en dur en FR.
 */
class ApiLocalizationAuthTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_forgot_password_hint_is_localized_in_arabic(): void
    {
        $response = $this->withHeader('Accept-Language', 'ar')
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk();
        $this->assertSame(
            'إذا كان هناك حساب مرتبط بهذا البريد الإلكتروني، فقد تم إرسال رابط إعادة تعيين كلمة المرور.',
            $response->json('localized_message')
        );
    }

    public function test_forgot_password_hint_is_localized_in_english(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk();
        $this->assertSame(
            'If an account exists for this email, a password reset link has been sent.',
            $response->json('localized_message')
        );
    }

    public function test_reset_password_invalid_token_is_localized_in_english(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/reset-password', [
                'email' => 'nobody@example.com',
                'token' => 'invalid-token',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $body = $response->json();
        $message = $body['message'] ?? $body['localized_message'] ?? '';
        $this->assertSame('Invalid, expired or already used reset token.', $message);
    }

    public function test_platform_auth_suspended_message_is_localized_in_turkish(): void
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Suspended Admin',
            'email' => 'suspended@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $superAdmin->forceFill(['status' => 'suspended'])->save();

        $response = $this->withHeader('Accept-Language', 'tr')
            ->postJson('/api/v1/platform/auth/login', [
                'email' => 'suspended@leopardo.test',
                'password' => 'password123',
            ]);

        $body = $response->json();
        $message = $body['message'] ?? $body['localized_message'] ?? '';
        $this->assertStringContainsString('askıya alındı', $message);
    }
}
