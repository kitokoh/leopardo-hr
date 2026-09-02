<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Infrastructure\Services\ChargilyService;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Platform\Infrastructure\Services\WebhookEventRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly WebhookEventRegistry $registry) {}

    public function chargily(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('X-Chargily-Signature', '');

        $chargilyService = new ChargilyService;
        $data = $chargilyService->verifyWebhookSignature($payload, $sigHeader);

        if ($data === null) {
            Log::warning('Chargily Webhook: Invalid signature');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        // #5444 : idempotence persistée. Un événement `checkout.paid` rejoué
        // créait un SECOND Payment (double encaissement comptable) — la
        // réservation (source+event_id) garantit zéro effet double.
        $eventId = $this->registry->eventId(
            $payload,
            is_string($data['id'] ?? null) && $data['id'] !== ''
                ? $data['id']
                : (is_string($data['data']['id'] ?? null) && $data['data']['id'] !== '' ? $data['data']['id'] : null),
        );
        $replay = $this->registry->begin('chargily', $eventId, hash('sha256', $payload));

        if ($replay !== null) {
            $this->registry->logReplay('chargily', $eventId, $replay['code']);

            return new JsonResponse(
                $this->registry->replayBody($replay['body'], ['received' => true, 'replayed' => true]),
                $replay['code'],
            );
        }

        try {
            $type = is_string($data['type'] ?? null) ? $data['type'] : '';

            Log::info('Chargily webhook received', ['type' => $type]);

            if ($type === 'checkout.paid') {
                $checkout = is_array($data['data'] ?? null) ? $data['data'] : [];
                $invoiceNumber = is_string($checkout['metadata']['invoice_number'] ?? null) ? $checkout['metadata']['invoice_number'] : null;

                if ($invoiceNumber !== null) {
                    $invoice = Invoice::where('number', $invoiceNumber)->first();
                    if ($invoice !== null) {
                        // DEP-BC21 #6248 : paiement via la machine à états. Une
                        // facture déjà payée/annulée ne peut pas être re-payée :
                        // on acquitte le webhook (idempotence #5444) sans créer
                        // de paiement fantôme.
                        try {
                            $invoice->transitionTo(InvoiceStatus::Paid, [
                                'paid_at' => now(),
                                'payment_method' => 'chargily',
                            ]);
                        } catch (InvalidArgumentException $e) {
                            Log::warning('Chargily: Transition de facture refusee - paiement non enregistre', [
                                'invoice_id' => $invoice->id,
                                'status' => $invoice->status,
                                'error' => $e->getMessage(),
                            ]);
                            $this->registry->complete('chargily', $eventId, 200, json_encode(['received' => true, 'skipped' => 'invoice_transition_refused']) ?: '');

                            return new JsonResponse(['received' => true, 'skipped' => 'invoice_transition_refused']);
                        }

                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'company_id' => $invoice->company_id,
                            'amount' => $invoice->total,
                            'currency' => $invoice->currency,
                            'method' => is_string($checkout['payment_method'] ?? null) ? $checkout['payment_method'] : 'cib',
                            'provider_reference' => is_string($checkout['id'] ?? null) ? $checkout['id'] : null,
                            'status' => 'completed',
                            'paid_at' => now(),
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            $this->registry->complete('chargily', $eventId, 200, json_encode(['received' => true]) ?: '');

            return new JsonResponse(['received' => true]);
        } catch (\Throwable $e) {
            $this->registry->release('chargily', $eventId);
            Log::error('Chargily Webhook: Error handling event', [
                'type' => is_string($data['type'] ?? null) ? $data['type'] : null,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }
    }
}
