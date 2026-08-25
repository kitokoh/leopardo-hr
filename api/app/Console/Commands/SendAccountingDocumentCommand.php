<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Accounting\Application\Actions\SendDocumentEmail;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Console\Command;

/**
 * Envoi d'un document comptable par email (issue #5225).
 *
 * Usage : php artisan accounting:send-document {document} {--email=client@exemple.com}
 */
class SendAccountingDocumentCommand extends Command
{
    protected $signature = 'accounting:send-document {document : ID du document comptable} {--email= : Email du destinataire (défaut : contact du document)}';

    protected $description = 'Envoie un document comptable par email (PDF + lien sécurisé)';

    public function handle(SendDocumentEmail $sendDocumentEmail): int
    {
        $document = AccountingDocument::query()->findOrFail((int) $this->argument('document'));

        $email = $this->option('email') ?? $document->contact?->email;

        if (! is_string($email) || $email === '') {
            $this->error('Aucun email destinataire (--email manquant ou contact sans email).');

            return self::FAILURE;
        }

        $token = $sendDocumentEmail->handle($document, $email);

        $this->info("Document #{$document->id} envoyé à {$email} — token {$token}.");

        return self::SUCCESS;
    }
}
