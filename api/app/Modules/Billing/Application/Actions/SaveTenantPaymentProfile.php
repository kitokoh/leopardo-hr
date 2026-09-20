<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use Illuminate\Database\ConnectionInterface;

/**
 * #7727 — création / édition d'un profil de paiement du tenant.
 *
 * Règles :
 *  - le TYPE est immuable après création (un profil bancaire ne devient pas
 *    un profil Stripe) ;
 *  - secrets WRITE-ONLY : seule une valeur non vide écrase l'existant, et
 *    seuls les champs de l'allowlist du type sont acceptés ;
 *  - un profil dont les secrets changent redescend en `draft` (les nouvelles
 *    clés n'ont pas été vérifiées) sauf s'il vient d'être créé ;
 *  - #7863 : le type `cash` (encaissement au local) n'exige AUCUN secret —
 *    ses allowlists (`SECRET_FIELDS['cash'] = []`,
 *    `DETAIL_FIELDS['cash'] = ['location']`) suffisent, le flux générique
 *    ci-dessous n'écrit alors que le label et les métadonnées déclaratives.
 */
class SaveTenantPaymentProfile
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  validé par le contrôleur
     */
    public function execute(array $payload, ?TenantPaymentProfile $profile = null, ?int $actorId = null): TenantPaymentProfile
    {
        return $this->db->transaction(function () use ($payload, $profile, $actorId): TenantPaymentProfile {
            $isNew = $profile === null;
            $profile ??= new TenantPaymentProfile([
                'type' => (string) $payload['type'],
                'status' => 'draft',
                'created_by' => $actorId,
            ]);

            if (array_key_exists('label', $payload)) {
                $profile->label = (string) $payload['label'];
            }

            $type = $profile->type;

            if (array_key_exists('details', $payload) && is_array($payload['details'])) {
                $details = $profile->details ?? [];
                foreach (TenantPaymentProfile::DETAIL_FIELDS[$type] ?? [] as $field) {
                    if (array_key_exists($field, $payload['details'])) {
                        $value = $payload['details'][$field];
                        $details[$field] = is_string($value) ? $value : '';
                    }
                }
                $profile->details = $details;
            }

            $secretsChanged = false;
            if (array_key_exists('secrets', $payload) && is_array($payload['secrets'])) {
                $secrets = $profile->secrets ?? [];
                foreach (TenantPaymentProfile::SECRET_FIELDS[$type] ?? [] as $field) {
                    $value = $payload['secrets'][$field] ?? null;
                    if (is_string($value) && $value !== '') {
                        $secrets[$field] = $value;
                        $secretsChanged = true;
                    }
                }
                $profile->secrets = $secrets === [] ? null : $secrets;
            }

            // Masques non secrets dérivés (affichage liste/factures sans
            // toucher aux secrets) : derniers caractères uniquement.
            if ($secretsChanged) {
                $details = $profile->details ?? [];
                $secrets = $profile->secrets ?? [];
                if ($type === 'bank_account' && isset($secrets['iban'])) {
                    $details['iban_last4'] = substr((string) $secrets['iban'], -4);
                }
                if ($type === 'mobile_money' && isset($secrets['phone_number'])) {
                    $details['phone_last4'] = substr((string) $secrets['phone_number'], -4);
                }
                $profile->details = $details;

                if (! $isNew) {
                    // Nouvelles clés = non vérifiées : retour en draft, le
                    // routage cesse d'utiliser ce profil jusqu'à réactivation.
                    $profile->status = 'draft';
                }
            }

            $profile->save();

            return $profile;
        });
    }
}
