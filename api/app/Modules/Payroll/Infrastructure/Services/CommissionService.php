<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerReferral;
use App\Modules\Payroll\Domain\Models\Commission;
use App\Modules\Payroll\Domain\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Calculate and record a commission for a successful payment.
     */
    public function recordCommissionForPayment(Payment $payment): ?Commission
    {
        if ($payment->status !== 'completed') {
            return null;
        }

        // Idempotency: Check if commission already exists for this payment
        if (Commission::where('payment_id', $payment->id)->exists()) {
            return null;
        }

        $company = Company::find($payment->company_id);
        if (! $company || ! $company->referrer_partner_id) {
            return null;
        }

        $partner = Partner::find($company->referrer_partner_id);
        if (! $partner || $partner->status !== 'active') {
            return null;
        }

        // 12-month commission limit rule
        $referral = PartnerReferral::where('company_id', $company->id)->first();
        if ($referral && $referral->referred_at->diffInMonths(now()) >= 12) {
            Log::info("Commission period (12 months) expired for company {$company->id}");

            return null;
        }

        // Base HT calculation: deduct taxes if partner tax_rate is set
        $paymentAmountInCents = (int) round((float) $payment->amount * 100);
        $taxRate = $partner->tax_rate; // bps
        $netAmountInCents = (int) floor(($paymentAmountInCents * 10000) / (10000 + $taxRate));

        // Snapshot of the commission rate
        $rate = $partner->default_commission_rate;

        // Commission Amount based on Net (HT)
        $commissionAmount = (int) floor(($netAmountInCents * $rate) / 10000);

        if ($commissionAmount <= 0) {
            return null;
        }

        // Exchange rate snapshot (Fake 1.0 if same currency for MVP)
        $exchangeRate = 1.0;

        // #3811 : deux webhooks de paiement concurrents peuvent franchir le
        // garde exists() ensemble — l'index unique payment_id (migration
        // 2026_08_15_add_unique_commissions_payment_id) tranche : la seconde
        // insertion échoue en 23505 et la commission est considérée déjà créée
        // (idempotent, plus de 500).
        try {
            $commission = Commission::create([
                'partner_id' => $partner->id,
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'amount' => $commissionAmount, // Total to pay to partner
                'net_amount' => $netAmountInCents, // HT Base
                'currency' => $payment->currency,
                'applied_rate' => $rate,
                'exchange_rate' => $exchangeRate,
                'original_amount' => $paymentAmountInCents,
                'original_currency' => $payment->currency,
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                Log::warning('Commission déjà enregistrée pour le paiement', ['payment_id' => $payment->id]);

                return null;
            }

            throw $e;
        }

        Log::info("Commission recorded: {$commission->id} for partner {$partner->id} (HT base: {$netAmountInCents})");

        return $commission;
    }
}
