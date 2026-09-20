<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * #7726 (BC-21 BILLING) — configuration éditable d'une passerelle de paiement
 * de la PLATEFORME (schéma public, une ligne par passerelle).
 *
 * `secrets` est chiffré au repos (cast `encrypted:array`) : la valeur en base
 * est un ciphertext Laravel (APP_KEY). Les secrets ne redescendent JAMAIS en
 * clair par l'API — voir `maskSecret()`.
 *
 * @property int $id
 * @property string $gateway
 * @property string $mode
 * @property array<string, mixed>|null $config
 * @property array<string, string>|null $secrets
 * @property bool $is_active
 * @property int|null $updated_by
 */
class PaymentGatewaySetting extends Model
{
    public const GATEWAYS = ['stripe', 'chargily'];

    public const MODES = ['test', 'live'];

    protected $table = 'payment_gateway_settings';

    protected $fillable = [
        'gateway',
        'mode',
        'config',
        'secrets',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'config' => 'array',
        // Chiffrement au repos : critère 1 de #7726.
        'secrets' => 'encrypted:array',
        'is_active' => 'boolean',
    ];

    // Défense en profondeur : même une sérialisation accidentelle du modèle
    // (log, réponse API non filtrée) n'expose pas les secrets déchiffrés.
    protected $hidden = ['secrets'];

    /**
     * Masque affichable d'un secret : préfixe reconnaissable + 4 derniers
     * caractères (`sk_live_••••1234`). Jamais la valeur complète.
     */
    public static function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $suffix = strlen($value) > 4 ? substr($value, -4) : '';

        // Préfixes Stripe reconnaissables (sk_live_, sk_test_, whsec_) : on
        // les conserve pour que l'admin identifie la clé sans la voir.
        if (preg_match('/^(sk_(?:live|test)_|rk_(?:live|test)_|whsec_|pk_(?:live|test)_)/', $value, $m) === 1) {
            return $m[1].'••••'.$suffix;
        }

        return '••••'.$suffix;
    }
}
