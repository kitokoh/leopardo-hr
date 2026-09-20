<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * #7727 (BC-21 BILLING) — profil de paiement d'un TENANT : clés PSP propres
 * (stripe_keys), coordonnées bancaires (bank_account) ou mobile money
 * (mobile_money). C'est ce qui permet à un client d'encaisser les paiements de
 * SES factures sur SON compte (routage `PaymentGatewayFactory`, Accounting).
 *
 * Isolation : `BelongsToCompany` (scope tenant fail-closed #3727) — un tenant
 * ne voit jamais les profils d'un autre.
 *
 * Sécurité : `secrets` chiffré au repos (cast `encrypted:array`) + `$hidden` ;
 * l'API ne renvoie que des masques (`maskedSecrets()`), write-only.
 *
 * `stripe_account_id` est RÉSERVÉ pour Stripe Connect (lot ultérieur).
 *
 * @property int $id
 * @property string $company_id
 * @property string $type
 * @property string $label
 * @property string $status
 * @property bool $is_default
 * @property array<string, mixed>|null $details
 * @property array<string, string>|null $secrets
 * @property string|null $stripe_account_id
 * @property int|null $created_by
 */
class TenantPaymentProfile extends Model
{
    use BelongsToCompany;

    public const TYPES = ['stripe_keys', 'bank_account', 'mobile_money'];

    public const STATUSES = ['draft', 'verified', 'active'];

    /** Champs secrets acceptés par type (allowlist write-only). */
    public const SECRET_FIELDS = [
        'stripe_keys' => ['secret_key', 'publishable_key', 'webhook_secret'],
        'bank_account' => ['iban'],
        'mobile_money' => ['phone_number'],
    ];

    /** Champs de détail non secrets acceptés par type. */
    public const DETAIL_FIELDS = [
        'stripe_keys' => [],
        'bank_account' => ['account_holder', 'bank_name', 'bic'],
        'mobile_money' => ['operator', 'account_holder'],
    ];

    protected $table = 'tenant_payment_profiles';

    protected $fillable = [
        'company_id',
        'type',
        'label',
        'status',
        'is_default',
        'details',
        'secrets',
        'stripe_account_id',
        'created_by',
    ];

    protected $casts = [
        'details' => 'array',
        // Chiffrement au repos : critère 1 de #7727.
        'secrets' => 'encrypted:array',
        'is_default' => 'boolean',
    ];

    // Défense en profondeur : une sérialisation accidentelle du modèle
    // n'expose jamais les secrets déchiffrés.
    protected $hidden = ['secrets'];

    /**
     * Masques affichables des secrets (`••••1234`) — jamais la valeur.
     *
     * @return array<string, array{configured: bool, mask: string|null}>
     */
    public function maskedSecrets(): array
    {
        $masked = [];
        $secrets = $this->secrets ?? [];

        foreach (self::SECRET_FIELDS[$this->type] ?? [] as $field) {
            $value = $secrets[$field] ?? '';
            $masked[$field] = [
                'configured' => $value !== '',
                'mask' => PaymentGatewaySetting::maskSecret($value !== '' ? $value : null),
            ];
        }

        return $masked;
    }

    /**
     * Représentation API du profil : détails non secrets + masques, JAMAIS un
     * secret en clair.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'status' => $this->status,
            'is_default' => $this->is_default,
            'details' => $this->details ?? (object) [],
            'secrets' => $this->maskedSecrets(),
            'stripe_account_id' => $this->stripe_account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
