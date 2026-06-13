<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\StripeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    public function subscription(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $subscription = Subscription::where('company_id', $user->company_id)
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['data' => null, 'message' => 'No active subscription.'], 404);
        }

        return (new SubscriptionResource($subscription))->response();
    }

    public function upgrade(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (!$user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'plan' => 'required|in:starter,business,enterprise',
            'payment_method' => 'nullable|in:stripe,bank_transfer,manual',
        ]);

        $subscription = Subscription::where('company_id', $user->company_id)
            ->latest()
            ->first();

        if (!$subscription) {
            $subscription = Subscription::create([
                'company_id' => $user->company_id,
                'plan' => $validated['plan'],
                'status' => 'active',
                'payment_method' => $validated['payment_method'] ?? 'manual',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        } else {
            $subscription->update([
                'plan' => $validated['plan'],
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        }

        return (new SubscriptionResource($subscription->fresh()))->response();
    }

    public function cancel(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (!$user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $subscription = Subscription::where('company_id', $user->company_id)
            ->latest()
            ->firstOrFail();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $validated['reason'] ?? null,
        ]);

        return (new SubscriptionResource($subscription->fresh()))->response();
    }

    public function renew(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (!$user->isManager()) {
            abort(403);
        }

        $subscription = Subscription::where('company_id', $user->company_id)
            ->latest()
            ->firstOrFail();

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancel_reason' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return (new SubscriptionResource($subscription->fresh()))->response();
    }

    public function invoices(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $invoices = Invoice::where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return InvoiceResource::collection($invoices)->response();
    }

    public function showInvoice(Request $request, int $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $invoice = Invoice::where('company_id', $user->company_id)
            ->with('payments')
            ->findOrFail($id);

        return (new InvoiceResource($invoice))->response();
    }

    public function invoicePdf(Request $request, int $id): Response
    {
        /** @var Employee $user */
        $user = $request->user();
        $invoice = Invoice::where('company_id', $user->company_id)->findOrFail($id);
        $company = Company::find($user->company_id);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'legalMentions' => '',
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf('facture_%s.pdf',
            $invoice->invoice_number ?? 'LEO-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT)
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * POST /billing/checkout
     *
     * Creates a Stripe Checkout Session for the company to subscribe or upgrade.
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $company = Company::findOrFail($user->company_id);

        $validated = $request->validate([
            'plan' => 'required|in:starter,business,enterprise',
            'success_url' => 'required|url|max:500',
            'cancel_url' => 'required|url|max:500',
        ]);

        if (!config('services.stripe.secret')) {
            return new JsonResponse([
                'error' => 'STRIPE_NOT_CONFIGURED',
                'message' => 'Le paiement en ligne n\'est pas encore configuré.',
            ], 503);
        }

        try {
            $session = $this->stripeService->createCheckoutSession(
                company: $company,
                plan: $validated['plan'],
                successUrl: $validated['success_url'],
                cancelUrl: $validated['cancel_url'],
            );

            return new JsonResponse([
                'data' => [
                    'checkout_url' => $session['url'],
                    'session_id' => $session['session_id'],
                ],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'CHECKOUT_FAILED',
                'message' => 'Impossible de créer la session de paiement.',
            ], 500);
        }
    }

    /**
     * GET /billing/portal
     *
     * Creates a Stripe Customer Portal session for the manager to manage
     * their subscription (update payment method, view invoices, cancel).
     */
    public function customerPortal(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $company = Company::findOrFail($user->company_id);

        $stripeCustomerId = $company->metadata['stripe_customer_id'] ?? null;

        if (!$stripeCustomerId) {
            return new JsonResponse([
                'error' => 'NO_STRIPE_CUSTOMER',
                'message' => 'Aucun compte de paiement associé. Souscrivez d\'abord à un plan.',
            ], 404);
        }

        $validated = $request->validate([
            'return_url' => 'required|url|max:500',
        ]);

        try {
            $portalUrl = $this->stripeService->createPortalSession(
                stripeCustomerId: $stripeCustomerId,
                returnUrl: $validated['return_url'],
            );

            return new JsonResponse([
                'data' => [
                    'portal_url' => $portalUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'PORTAL_FAILED',
                'message' => 'Impossible d\'accéder au portail de facturation.',
            ], 500);
        }
    }
}

