<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Modules\Billing\Domain\Models\PartnerPayoutRequest;
use App\Modules\Payroll\Domain\Models\Commission;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerAuditLog;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Exceptions\DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerService
{
    /**
     * Soumet une candidature de partenaire.
     *
     * Anti-doublon #2999 : la contrainte unique partners_user_id_unique
     * (migration 2026_08_15_000006) est le verrou définitif — deux
     * candidatures simultanées ne peuvent pas créer deux partenaires.
     */
    public function apply(int $userId, array $details): Partner
    {
        $encryptor = app(\App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor::class);
        $encryptedDetails = isset($details['payment_details'])
            ? $encryptor->encrypt($details['payment_details'])
            : null;

        try {
            // PostgreSQL aborts the current transaction after a unique
            // violation. A nested transaction creates a savepoint, allowing
            // the expected ALREADY_EXISTS domain error to leave the caller's
            // transaction usable (notably in race-condition tests).
            return DB::transaction(fn (): Partner => Partner::create([
                'user_id' => $userId,
                'referral_code' => strtoupper(\Illuminate\Support\Str::random(10)),
                'application_status' => 'pending',
                'status' => 'suspended', // Suspended until approved
                'type' => $details['type'] ?? 'individual',
                // Issue #4186 : coordonnées de candidature persistées (colonnes additives 2026_08_16_000001).
                'name' => $details['name'] ?? null,
                'email' => $details['email'] ?? null,
                'phone' => $details['phone'] ?? null,
                'website' => $details['website'] ?? null,
                'commission_rate' => $details['commission_rate'] ?? null,
                'payment_details' => $encryptedDetails,
            ]));
        } catch (QueryException $e) {
            // 23505 = violation de contrainte unique (race gagnée par l'autre requête).
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'partners_user_id_unique')) {
                throw new DomainException('Déjà partenaire.', 400, 'ALREADY_EXISTS');
            }

            throw $e;
        }
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
     * Rejette un partenaire.
     */
    public function reject(Partner $partner, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($partner, $adminId, $reason) {
            $partner->update([
                'application_status' => 'rejected',
                'status' => 'suspended',
            ]);

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => Partner::class,
                'auditable_id' => (string) $partner->id,
                'event' => 'application_rejected',
                'new_values' => ['status' => 'suspended', 'application_status' => 'rejected'],
                'reason' => $reason,
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

        \App\Modules\Billing\Domain\Models\PartnerReferral::updateOrCreate(
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
     *
     * #2999 : transaction + lockForUpdate sur le partenaire — les demandes
     * concurrentes se sérialisent et ne peuvent pas dépasser le solde.
     */
    public function requestPayout(Partner $partner, int $amountCents, string $currency): \App\Modules\Billing\Domain\Models\PartnerPayoutRequest
    {
        return DB::transaction(function () use ($partner, $amountCents, $currency) {
            // Verrou pessimiste : sérialise les demandes concurrentes.
            $lockedPartner = Partner::query()
                ->whereKey($partner->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Calcul du solde disponible (recalculé sous verrou).
            $totalEarned = $lockedPartner->commissions()->where('status', 'approved')->sum('amount');
            $totalRequested = \App\Modules\Billing\Domain\Models\PartnerPayoutRequest::where('partner_id', $lockedPartner->id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->sum('amount');

            $available = $totalEarned - $totalRequested;

            if ($amountCents > $available) {
                throw new \App\Exceptions\DomainException("Solde insuffisant.", 422, "INSUFFICIENT_BALANCE");
            }

            if ($amountCents < $lockedPartner->payout_threshold) {
                throw new \App\Exceptions\DomainException("Montant sous le seuil.", 422, "BELOW_PAYOUT_THRESHOLD");
            }

            return \App\Modules\Billing\Domain\Models\PartnerPayoutRequest::create([
                'partner_id' => $lockedPartner->id,
                'amount' => $amountCents,
                'currency' => $currency,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Met à jour le statut d'une demande de paiement avec Audit Trail.
     */
    public function updatePayoutStatus(\App\Modules\Billing\Domain\Models\PartnerPayoutRequest $payout, string $newStatus, int $adminId, string $reason): void
    {
        $oldStatus = $payout->status;

        // #3859 : transitions de statut gardées (allowlist). Avant, le service
        // acceptait n'importe quel changement de statut (ex. paid → pending),
        // ce qui pouvait réouvrir une demande déjà payée. Le solde disponible
        // (`requestPayout`) déduit les demandes pending/approved/paid — une
        // demande repassée en pending serait donc comptée deux fois dans les
        // demandes en attente sans verrou de transition.
        $allowedTransitions = [
            'pending' => ['paid', 'rejected', 'approved'],
            'approved' => ['paid', 'rejected'],
            'rejected' => ['pending'],
            'paid' => [],
        ];

        if (! in_array($newStatus, $allowedTransitions[$oldStatus] ?? [], true)) {
            throw new \App\Exceptions\DomainException(
                "Transition de statut invalide: {$oldStatus} -> {$newStatus}.",
                422,
                'INVALID_PAYOUT_TRANSITION'
            );
        }

        DB::transaction(function () use ($payout, $newStatus, $adminId, $reason, $oldStatus) {
            $payout->update([
                'status' => $newStatus,
                'processed_at' => now(),
                'admin_notes' => $reason,
            ]);

            PartnerAuditLog::create([
                'admin_id' => $adminId,
                'auditable_type' => \App\Modules\Billing\Domain\Models\PartnerPayoutRequest::class,
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
        $encryptor = app(\App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor::class);
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

