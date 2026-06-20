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
        DB::transaction(function () use ($partner, $adminId) {
            $partner->update([
                'application_status' => 'approved',
                'status' => 'active',
            ]);

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Partner::class,
                'auditable_id' => (string) $partner->id,
                'event' => 'application_approved',
                'new_values' => ['status' => 'active', 'application_status' => 'approved'],
                'reason' => 'Partner application approved by admin.',
            ]);
        });
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
        $company->save();

        \App\Models\PartnerReferral::updateOrCreate(
            ['company_id' => $company->id],
            [
                'partner_id' => $partner->id,
                'referred_at' => now(),
            ]
        );

        return true;
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
            throw new \App\Exceptions\DomainException("Solde insuffisant.", 422, "INSUFFICIENT_BALANCE");
        }

        if ($amountCents < $partner->payout_threshold) {
            throw new \App\Exceptions\DomainException("Montant sous le seuil.", 422, "BELOW_PAYOUT_THRESHOLD");
        }

        return \App\Models\PartnerPayoutRequest::create([
            'partner_id' => $partner->id,
            'amount' => $amountCents,
            'currency' => $currency,
            'status' => 'pending',
        ]);
    }

    /**
     * Met à jour le statut d'une demande de paiement avec Audit Trail.
     */
    public function updatePayoutStatus(\App\Models\PartnerPayoutRequest $payout, string $newStatus, int $adminId, string $reason): void
    {
        $oldStatus = $payout->status;

        DB::transaction(function () use ($payout, $newStatus, $adminId, $reason, $oldStatus) {
            $payout->update([
                'status' => $newStatus,
                'processed_at' => now(),
                'admin_notes' => $reason,
            ]);

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => \App\Models\PartnerPayoutRequest::class,
                'auditable_id' => (string) $payout->id,
                'event' => 'payout_status_change',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus],
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Met à jour les coordonnées de paiement avec Audit Trail.
     */
    public function updatePaymentDetails(Partner $partner, string $details, int $adminId, string $reason): void
    {
        $encryptor = app(\App\Services\Security\SensitiveDataEncryptor::class);
        $encrypted = $encryptor->encrypt($details);

        DB::transaction(function () use ($partner, $encrypted, $adminId, $reason) {
            $partner->update(['payment_details' => $encrypted]);

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Partner::class,
                'auditable_id' => (string) $partner->id,
                'event' => 'payment_details_updated',
                'reason' => $reason,
            ]);
        });
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
