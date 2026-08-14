<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Régression #1766 — `MAIL_URL=` (vide) dans .env.example cassait TOUS les
 * envois d'email : `Illuminate\Mail\MailManager::getConfig()` fait
 * `isset($config['url'])` (vrai même pour une chaîne vide), parse le DSN vide
 * et écrase `transport` par null → « Unsupported mail transport [] » → HTTP 500.
 */
class MailEmptyUrlRegressionTest extends TestCase
{
    public function test_empty_mail_url_without_fix_breaks_mail_resolution(): void
    {
        // Simule `MAIL_URL=` présent mais vide dans l'environnement.
        config()->set('mail.mailers.smtp.url', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported mail transport []');

        /** @var MailManager $manager */
        $manager = app('mail.manager');
        $manager->mailer('smtp');
    }

    public function test_normalize_empty_mailer_urls_makes_empty_url_harmless(): void
    {
        config()->set('mail.mailers.smtp.url', '');

        AppServiceProvider::normalizeEmptyMailerUrls();

        $this->assertNull(config('mail.mailers.smtp.url'));

        /** @var MailManager $manager */
        $manager = app('mail.manager');
        $mailer = $manager->mailer('smtp');

        $this->assertInstanceOf(Mailer::class, $mailer);

        // Le transport smtp est bien résolu (plus de DSN vide qui écrase driver).
        $transport = $manager->createSymfonyTransport(config('mail.mailers.smtp'));
        $this->assertNotInstanceOf(InvalidArgumentException::class, $transport);
    }

    public function test_mail_send_with_empty_url_does_not_throw_transport_exception(): void
    {
        Mail::fake();

        config()->set('mail.mailers.smtp.url', '');
        AppServiceProvider::normalizeEmptyMailerUrls();

        // Envoyer un email ne doit plus lever « Unsupported mail transport [] » :
        // le transport smtp est résolu et le send passe par la file fake.
        $mailable = new class extends \Illuminate\Mail\Mailable
        {
            public function build(): \Illuminate\Mail\Mailable
            {
                return $this->html('<p>Regression #1766</p>');
            }
        };

        Mail::to('test@example.test')->send($mailable);

        Mail::assertSentCount(1);
    }
}
