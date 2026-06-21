<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Company;
use App\Models\Partner;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Calculate and record a commission for a successful payment.
     */
    public function recordCommissionForPayment(Payment $payment): ?Commission
    {
        if ($payment->status !== 'completed') {
            throw new \RuntimeException('Failed A');
        }

        // Idempotency: Check if commission already exists for this payment
        if (Commission::where('payment_id', $payment->id)->exists()) {
            throw new \RuntimeException('Failed B: payment_id ' . $payment->id);
        }

        $company = Company::find($payment->company_id);
        if (!$company || !$company->referrer_partner_id) {
            throw new \RuntimeException('Failed C: no company or no referrer_partner_id. Company: ' . json_encode($company));
        }

        $partner = Partner::find($company->referrer_partner_id);
        if (!$partner || $partner->status !== 'active') {
            throw new \RuntimeException('Failed D: no partner or not active. Partner: ' . json_encode($partner));
        }

        // 12-month commission limit rule
        $referral = \App\Models\PartnerReferral::where('company_id', $company->id)->first();
        if ($referral && $referral->referred_at->diffInMonths(now()) >= 12) {
            throw new \RuntimeException('Failed E: 12-month limit');
        }

        $taxRate = $partner->tax_rate;
        $rate = $partner->default_commission_rate;

        // amount is e.g. 120.00. We want net HT. If tax_rate is 2000 (20%), net = amount / 1.2
        $taxMultiplier = 1 + ($taxRate / 10000);
        $netAmount = (float) $payment->amount / $taxMultiplier;

        // Commission is calculated on HT amount in cents
        $netAmountInCents = (int) round($netAmount * 100);
        $commissionAmount = (int) floor(($netAmountInCents * $rate) / 10000);

        if ($commissionAmount <= 0) {
            throw new \RuntimeException('Failed F: commissionAmount <= 0');
        }

        // Exchange rate snapshot (Fake 1.0 if same currency for MVP)
        $exchangeRate = 1.0;

        $paymentAmountInCents = (int) round((float) $payment->amount * 100);

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

        Log::info("Commission recorded: {$commission->id} for partner {$partner->id} (HT base: {$netAmountInCents})");

        return $commission;
    }
}
