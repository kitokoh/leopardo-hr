<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Modules\Billing\Infrastructure\Services\InvoiceMailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #7763 — envoie le reçu de paiement (PDF joint) au principal du tenant quand
 * une facture transite vers `paid`.
 *
 * L'échec d'envoi est journalisé mais ne remonte JAMAIS : un email ne doit
 * pas faire échouer un webhook de paiement (sémantique 500 → retry provider,
 * #2668) ni invalider la transition déjà persistée.
 */
class SendInvoicePaymentReceipt
{
    public function __construct(private readonly InvoiceMailer $mailer) {}

    public function handle(InvoicePaid $event): void
    {
        try {
            $this->mailer->sendPaymentReceipt($event->invoice);
        } catch (Throwable $e) {
            Log::warning('Billing: échec de l\'envoi du reçu de paiement', [
                'invoice_id' => $event->invoice->id,
                'company_id' => $event->invoice->company_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
