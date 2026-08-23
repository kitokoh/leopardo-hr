<?php

declare(strict_types=1);

namespace App\Mail;

use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email d'envoi d'un document comptable au contact client (issue #5225).
 *
 * PDF en pièce jointe (disque privé) + lien sécurisé vers le portail
 * (token + expiration — RGPD : accès limité au document partagé).
 */
class DocumentShareMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $portalUrl;

    public readonly string $documentName;

    public function __construct(
        public readonly AccountingDocumentShare $share,
        public readonly ?string $pdfPath = null,
        public readonly ?string $pdfName = null,
    ) {
        $this->portalUrl = rtrim((string) (config('app.frontend_url') ?? config('app.url') ?? ''), '/')
            .'/documents/shared/'.$share->share_token;
        $this->documentName = $pdfName ?? $share->document?->number ?? '';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('accounting.email_subject', ['number' => $this->documentName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-share',
            with: [
                'share' => $this->share,
                'portalUrl' => $this->portalUrl,
                'documentName' => $this->documentName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfPath === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('private', $this->pdfPath)->as($this->pdfName ?? 'document.pdf'),
        ];
    }
}
