<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #7727 (BC-21 BILLING) — implémentation du contrat partagé de résolution des
 * profils de paiement d'un tenant. Consommé par Accounting
 * (PaymentGatewayFactory / StripePaymentGateway) via le binding de
 * BillingServiceProvider — jamais par import direct (garde #5584).
 */
class TenantPaymentProfileResolver implements TenantPaymentProfileResolverInterface
{
    public function activeStripeCredentials(): ?array
    {
        try {
            // Contexte tenant COURANT : BelongsToCompany scope la requête —
            // sans compagnie courante sur la surface tenant, fail-closed #3727.
            /** @var TenantPaymentProfile|null $profile */
            $profile = TenantPaymentProfile::query()
                ->where('type', 'stripe_keys')
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->first();
        } catch (Throwable $e) {
            // Table absente (déploiement en cours de migration) : fallback
            // comportement historique — clés plateforme.
            Log::warning('TenantPaymentProfileResolver: lecture impossible — fallback clés plateforme', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($profile === null) {
            return null;
        }

        $secrets = $profile->secrets ?? [];
        $secretKey = $secrets['secret_key'] ?? '';

        if ($secretKey === '') {
            return null;
        }

        return [
            'profile_id' => (int) $profile->id,
            'secret_key' => $secretKey,
            'webhook_secret' => $secrets['webhook_secret'] ?? '',
            'stripe_account_id' => $profile->stripe_account_id,
        ];
    }

    public function stripeWebhookSecretForCompany(string $companyId): ?string
    {
        if ($companyId === '') {
            return null;
        }

        try {
            // Contexte webhook PUBLIC (pas de tenant courant) : requête hors
            // scope global, mais TOUJOURS filtrée explicitement par la
            // compagnie désignée — jamais de balayage cross-tenant.
            /** @var TenantPaymentProfile|null $profile */
            $profile = TenantPaymentProfile::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('type', 'stripe_keys')
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->first();
        } catch (Throwable $e) {
            Log::warning('TenantPaymentProfileResolver: lecture webhook impossible', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $secrets = $profile->secrets ?? [];
        $secret = $secrets['webhook_secret'] ?? '';

        return $secret !== '' ? $secret : null;
    }

    public function stripeCredentialsForCompany(string $companyId): ?array
    {
        $profile = $this->activeProfileForCompany($companyId, 'stripe_keys');

        if ($profile === null) {
            return null;
        }

        $secrets = $profile->secrets ?? [];
        $secretKey = $secrets['secret_key'] ?? '';

        if ($secretKey === '') {
            return null;
        }

        return [
            'profile_id' => (int) $profile->id,
            'secret_key' => $secretKey,
            'webhook_secret' => $secrets['webhook_secret'] ?? '',
            'stripe_account_id' => $profile->stripe_account_id,
        ];
    }

    public function activeMobileMoneyProfileForCompany(string $companyId): ?array
    {
        $profile = $this->activeProfileForCompany($companyId, 'mobile_money');

        if ($profile === null) {
            return null;
        }

        $secrets = $profile->secrets ?? [];
        $details = $profile->details ?? [];
        $operator = is_string($details['operator'] ?? null) ? $details['operator'] : '';
        $phoneNumber = $secrets['phone_number'] ?? '';

        if ($operator === '' || $phoneNumber === '') {
            return null;
        }

        return [
            'profile_id' => (int) $profile->id,
            'operator' => $operator,
            'phone_number' => $phoneNumber,
        ];
    }

    /**
     * Profil ACTIF d'un type donné pour une compagnie DONNÉE — contexte
     * public (pas de tenant courant) : requête hors scope global mais
     * TOUJOURS filtrée explicitement par la compagnie désignée (#7728).
     */
    private function activeProfileForCompany(string $companyId, string $type): ?TenantPaymentProfile
    {
        if ($companyId === '') {
            return null;
        }

        try {
            /** @var TenantPaymentProfile|null $profile */
            $profile = TenantPaymentProfile::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('type', $type)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->first();

            return $profile;
        } catch (Throwable $e) {
            // Table absente (déploiement en cours de migration) : fail-closed
            // côté appelant (paiement en ligne non configuré), jamais de 500.
            Log::warning('TenantPaymentProfileResolver: lecture par compagnie impossible', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
