<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use App\Modules\CRM\Infrastructure\Services\LogEmailProvider;
use App\Modules\CRM\Infrastructure\Services\MailEmailProvider;
use App\Modules\CRM\Infrastructure\Services\ResendEmailProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #7752 — fournisseur email Resend (envoi réel production).
 *
 * Couvre : envoi réussi (message_id Resend), fail-closed sans clé ou sans
 * expéditeur, erreur API convertie en résultat failed (jamais d'exception),
 * sélection du provider par CRM_EMAIL_PROVIDER.
 */
class ResendEmailProviderTest extends TestCase
{
    private function message(): EmailMessage
    {
        return new EmailMessage('dest@example.com', 'Sujet', 'Corps du message.');
    }

    public function test_send_success_returns_resend_message_id(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('crm.email.from_address', 'noreply@leopardo.example');

        Http::fake([
            'api.resend.com/emails' => Http::response(['id' => 'resend-msg-1'], 200),
        ]);

        $result = (new ResendEmailProvider)->send($this->message());

        $this->assertTrue($result->isDelivered());
        $this->assertSame('resend-msg-1', $result->messageId);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.resend.com/emails'
                && $request['from'] === 'noreply@leopardo.example'
                && $request['to'] === ['dest@example.com']
                && $request['subject'] === 'Sujet'
                && $request->hasHeader('Authorization', 'Bearer re_test_key');
        });
    }

    public function test_missing_key_fails_closed_without_http_call(): void
    {
        config()->set('services.resend.key', null);

        Http::fake();

        $result = (new ResendEmailProvider)->send($this->message());

        $this->assertSame('failed', $result->status);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('RESEND_KEY', $result->error);
        Http::assertNothingSent();
    }

    public function test_missing_from_address_fails_closed(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('crm.email.from_address', null);
        config()->set('mail.from.address', null);

        Http::fake();

        $result = (new ResendEmailProvider)->send($this->message());

        $this->assertSame('failed', $result->status);
        Http::assertNothingSent();
    }

    public function test_api_error_is_converted_to_failed_result(): void
    {
        config()->set('services.resend.key', 're_test_key');
        config()->set('crm.email.from_address', 'noreply@leopardo.example');

        Http::fake([
            'api.resend.com/emails' => Http::response(['message' => 'Invalid domain'], 422),
        ]);

        $result = (new ResendEmailProvider)->send($this->message());

        $this->assertSame('failed', $result->status);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('Invalid domain', $result->error);
    }

    public function test_provider_binding_follows_configuration(): void
    {
        config()->set('crm.email.provider', 'resend');
        $this->assertInstanceOf(ResendEmailProvider::class, app(EmailProviderInterface::class));

        config()->set('crm.email.provider', 'mail');
        $this->assertInstanceOf(MailEmailProvider::class, app(EmailProviderInterface::class));

        config()->set('crm.email.provider', 'log');
        $this->assertInstanceOf(LogEmailProvider::class, app(EmailProviderInterface::class));
    }
}
