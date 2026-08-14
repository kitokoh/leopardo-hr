<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\TestCase;

/**
 * Issue #1766 — Une `MAIL_URL` vide (présente dans `.env.example` par défaut,
 * ou définie à vide dans un environnement) cassait TOUS les envois d'email :
 * Laravel 12 parse le DSN vide et écrase `transport` par null →
 * « Unsupported mail transport [] » (500 sur invitation employé, reset
 * password, notifications…).
 *
 * Correctif : `config/mail.php` ne traite plus une URL vide comme une URL
 * (`env('MAIL_URL') ?: null`) — le transport smtp configuré est conservé.
 */
class MailUrlEmptyGuardTest extends TestCase
{
    public function test_empty_mail_url_exposes_null_config(): void
    {
        // Avec MAIL_URL absent/vide dans l'environnement (cas par défaut),
        // la config exposée doit être null (et non '').
        $this->assertNull(config('mail.mailers.smtp.url'));
    }

    public function test_smtp_mailer_resolves_when_mail_url_is_empty(): void
    {
        config()->set('mail.default', 'smtp');

        // Avant le correctif : InvalidArgumentException
        // « Unsupported mail transport [] » à la résolution du mailer.
        $mailer = app('mail.manager')->mailer('smtp');

        $transport = $mailer->getSymfonyTransport();
        $this->assertInstanceOf(SmtpTransport::class, $transport);
    }
}
