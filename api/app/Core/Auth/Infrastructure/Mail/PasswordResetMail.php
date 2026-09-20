<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #2626 — email de réinitialisation de mot de passe.
 * Le token est envoyé en clair (usage unique, 60 min) ; seul son hash est
 * stocké en base.
 *
 * i18n (audit S-5 #1665, résiduel 2026-08-17) : sujet et corps résolus via
 * les catalogues `emails.email_password_reset_*` (fr/en/ar/tr). La locale
 * applicative est celle posée par SetLocale au moment de l'envoi (requête
 * API) ; les clés existent dans les 4 catalogues.
 *
 * #7854 : envoyé en file d'attente (queue `emails`, drainée par le worker
 * prod — cf. render.prod.yaml) au lieu d'un envoi synchrone dans la requête
 * HTTP. La locale de la requête est capturée à la construction : sur le
 * worker, `app()->getLocale()` retomberait sinon sur la locale par défaut.
 */
class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {
        $this->onQueue('emails');

        // Le token est écrit en base juste avant l'envoi : si un appelant
        // enveloppe un jour ce flux dans une transaction, le job ne doit
        // partir qu'après commit (no-op hors transaction).
        $this->afterCommit();

        // `Mailable::send()` (exécuté sur le worker) enveloppe envelope()/
        // content() dans withLocale($this->locale) : app()->getLocale()
        // y retournera bien la locale capturée ici (requête API, SetLocale).
        $this->locale(app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('password_reset', app()->getLocale(), [
                ':name' => $this->email,
                ':brand' => config('mail.brand.name'),
            ])->subject,
        );
    }

    public function content(): Content
    {
        // #7347 — le contenu éditable est résolu ICI (PHP) et passé à la vue :
        // la vue ne fait que rendre, elle ne construit aucune table de
        // variables (et n'ajoute donc aucun littéral dans un template).
        $tpl = app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('password_reset', app()->getLocale(), [
            ':name' => $this->email,
            ':brand' => \App\Core\Mail\MailBrand::name(),
        ]);

        return new Content(
            view: 'mail.password-reset',
            with: [
                'token' => $this->token,
                'email' => $this->email,
                'userName' => $this->email,
                'tpl' => $tpl,
            ],
        );
    }
}
