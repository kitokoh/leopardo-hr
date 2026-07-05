<?php

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function chargily(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('X-Chargily-Signature', '');

        $chargilyService = new \App\Modules\Billing\Infrastructure\Services\ChargilyService();
        $data = $chargilyService->verifyWebhookSignature($payload, $sigHeader);

        if ($data === null) {
            Log::warning('Chargily Webhook: Invalid signature');
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        $type = $data['type'] ?? '';

        Log::info('Chargily webhook received', ['type' => $type]);

        if ($type === 'checkout.paid') {
            $checkout = $data['data'] ?? [];
            $invoiceNumber = $checkout['metadata']['invoice_number'] ?? null;

            if ($invoiceNumber) {
                $invoice = Invoice::where('number', $invoiceNumber)->first();
                if ($invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => 'chargily',
                    ]);

                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'company_id' => $invoice->company_id,
                        'amount' => $invoice->total,
                        'currency' => $invoice->currency,
                        'method' => $checkout['payment_method'] ?? 'cib',
                        'provider_reference' => $checkout['id'] ?? null,
                        'status' => 'completed',
                        'paid_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return new JsonResponse(['received' => true]);
    }
}

