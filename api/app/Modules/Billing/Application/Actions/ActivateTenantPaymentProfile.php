<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * #7727 — activation d'un profil de paiement du tenant.
 *
 * Un seul profil ACTIF par type : activer un profil désactive (repasse en
 * `verified`) les autres profils actifs du même type — c'est lui que le
 * routage des encaissements (PaymentGatewayFactory) résout.
 */
class ActivateTenantPaymentProfile
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    public function execute(TenantPaymentProfile $profile): TenantPaymentProfile
    {
        // Un profil Stripe sans clé secrète ne peut pas encaisser : refus
        // explicite plutôt qu'un routage qui échouera en production.
        if ($profile->type === 'stripe_keys') {
            $secrets = $profile->secrets ?? [];
            if (! isset($secrets['secret_key']) || $secrets['secret_key'] === '') {
                throw ValidationException::withMessages([
                    'secrets.secret_key' => [__('validation.required', ['attribute' => 'secret_key'])],
                ]);
            }
        }

        return $this->db->transaction(function () use ($profile): TenantPaymentProfile {
            TenantPaymentProfile::query()
                ->where('type', $profile->type)
                ->where('status', 'active')
                ->whereKeyNot($profile->id)
                ->update(['status' => 'verified']);

            $profile->status = 'active';
            $profile->is_default = true;

            TenantPaymentProfile::query()
                ->where('type', $profile->type)
                ->whereKeyNot($profile->id)
                ->update(['is_default' => false]);

            $profile->save();

            return $profile;
        });
    }
}
