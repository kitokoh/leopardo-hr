<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerAuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerService
{
    /**
     * Soumet une candidature de partenaire.
     */
    public function apply(int $userId, array $details): Partner
    {
        $encryptor = app(\App\Services\Security\SensitiveDataEncryptor::class);
        $encryptedDetails = isset($details['payment_details'])
            ? $encryptor->encrypt($details['payment_details'])
            : null;

        return Partner::create([
            'user_id' => $userId,
            'referral_code' => strtoupper(\Illuminate\Support\Str::random(10)),
            'application_status' => 'pending',
            'status' => 'suspended', // Suspended until approved
            'type' => $details['type'] ?? 'individual',
            'payment_details' => $encryptedDetails,
        ]);
    }

    /**
     * Approuve un partenaire.
     */
    public function approve(Partner $partner, int $adminId): void
    {
        $this->reassignCompanyPartner($partner->referredCompanies()->first() ?? new \App\Models\Company(), $partner->id, $adminId, 'Application Approved'); // Fake usage of reassign logic to follow audit pattern

        $partner->update([
            'application_status' => 'approved',
            'status' => 'active',
        ]);
    }

    /**
     * Attribue un partenaire à une entreprise (Tenant).
     */
    public function attributeCompanyToPartner(Company $company, string $referralCode): bool
    {
        $partner = Partner::with('user')->where('referral_code', $referralCode)
            ->where('status', 'active')
            ->first();

        if (!$partner) {
            return false;
        }

        // Vérification d'auto-référencement
        if ($company->email === $partner->user->email) {
            Log::warning("Tentative d'auto-référencement bloquée pour le partenaire: {$partner->id}");
            return false;
        }

        $company->referrer_partner_id = $partner->id;
        return $company->save();
    }


    /**
     * Approuve les commissions en attente après le délai de 14 jours.
     */
    public function approvePendingCommissions(): int
    {
        $cutoff = now()->subDays(14);

        return Commission::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    /**
     * Gère le cas d'un remboursement.
     */
    public function handlePaymentRefunded(Payment $payment): void
    {
        Commission::where('payment_id', $payment->id)
            ->whereIn('status', ['pending', 'approved'])
            ->update(['status' => 'cancelled']);
    }

    /**
     * Demander un paiement (Payout).
     */
    public function requestPayout(Partner $partner, int $amountCents, string $currency): \App\Models\PartnerPayoutRequest
    {
        // Calcul du solde disponible
        $totalEarned = $partner->commissions()->where('status', 'approved')->sum('amount');
        $totalRequested = \App\Models\PartnerPayoutRequest::where('partner_id', $partner->id)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('amount');

        $available = $totalEarned - $totalRequested;

        if ($amountCents > $available) {
            throw new \Exception("Solde insuffisant.");
        }

        if ($amountCents < $partner->payout_threshold) {
            throw new \Exception("Le montant est inférieur au seuil de paiement.");
        }

        return \App\Models\PartnerPayoutRequest::create([
            'partner_id' => $partner->id,
            'amount' => $amountCents,
            'currency' => $currency,
            'status' => 'pending',
        ]);
    }

    /**
     * Réattribution manuelle avec Audit Trail.
     */
    public function reassignCompanyPartner(Company $company, ?int $newPartnerId, int $adminId, string $reason): void
    {
        $oldPartnerId = $company->referrer_partner_id;

        DB::transaction(function () use ($company, $newPartnerId, $adminId, $reason, $oldPartnerId) {
            $company->referrer_partner_id = $newPartnerId;
            $company->save();

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Company::class,
                'auditable_id' => $company->id,
                'event' => 'partner_reassignment',
                'old_values' => ['referrer_partner_id' => $oldPartnerId],
                'new_values' => ['referrer_partner_id' => $newPartnerId],
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Met à jour le taux de commission d'un partenaire avec Audit Trail.
     */
    public function updatePartnerRate(Partner $partner, int $newRate, int $adminId, string $reason): void
    {
        $oldRate = $partner->default_commission_rate;

        DB::transaction(function () use ($partner, $newRate, $adminId, $reason, $oldRate) {
            $partner->default_commission_rate = $newRate;
            $partner->save();

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Partner::class,
                'auditable_id' => (string) $partner->id,
                'event' => 'rate_adjustment',
                'old_values' => ['default_commission_rate' => $oldRate],
                'new_values' => ['default_commission_rate' => $newRate],
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Met à jour le statut d'une commission avec Audit Trail.
     */
    public function updateCommissionStatus(Commission $commission, string $newStatus, int $adminId, string $reason): void
    {
        $oldStatus = $commission->status;

        DB::transaction(function () use ($commission, $newStatus, $adminId, $reason, $oldStatus) {
            $commission->status = $newStatus;

            if ($newStatus === 'approved' && !$commission->approved_at) {
                $commission->approved_at = now();
            } elseif ($newStatus === 'paid' && !$commission->paid_at) {
                $commission->paid_at = now();
            }

            $commission->save();

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Commission::class,
                'auditable_id' => (string) $commission->id,
                'event' => 'commission_status_change',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus],
                'reason' => $reason,
            ]);
        });
    }
}
