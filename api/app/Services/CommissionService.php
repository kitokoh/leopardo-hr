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
            return null;
        }

        $company = Company::find($payment->company_id);
        if (!$company || !$company->referrer_partner_id) {
            return null;
        }

        $partner = Partner::find($company->referrer_partner_id);
        if (!$partner || $partner->status !== 'active') {
            return null;
        }

        // Snapshot of the rate
        $rate = $partner->default_commission_rate;

        // Ensure we work with cents (Payment amount is decimal string/float in Core RH)
        $paymentAmountInCents = (int) round((float) $payment->amount * 100);

        // Amount in cents: (Payment Amount Cents * Rate bps) / 10000 (bps conversion)
        $commissionAmount = (int) floor(($paymentAmountInCents * $rate) / 10000);

        if ($commissionAmount <= 0) {
            return null;
        }

        $commission = Commission::create([
            'partner_id' => $partner->id,
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'amount' => $commissionAmount,
            'currency' => $payment->currency,
            'applied_rate' => $rate,
            'status' => 'pending',
        ]);

        Log::info("Commission recorded: {$commission->id} for partner {$partner->id}");

        return $commission;
    }
}
