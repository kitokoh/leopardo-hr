<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #9838 (BC-14 INTEGRATION) — connexion d'un TENANT à un service externe.
 *
 * Une ligne = un compte externe qu'un client branche sur son espace : son site
 * WordPress/WooCommerce, sa page produit Facebook, sa boutique Shopify, sa
 * boutique Instagram, son compte TikTok Shop…
 *
 * Isolation : `BelongsToCompany` (scope tenant fail-closed #3727) — un tenant
 * ne voit jamais les connexions d'un autre, et une requête sans contexte
 * tenant sur la surface API lève au lieu de fuiter.
 *
 * SÉCURITÉ : `secrets` est chiffré au repos (cast `encrypted:array`) et
 * `$hidden` empêche une sérialisation accidentelle. L'API ne renvoie que des
 * masques (`••••1234`), en écriture seule : une valeur vide au PATCH conserve
 * le secret en place.
 *
 * @property int $id
 * @property string $company_id
 * @property string $catalog_key
 * @property string $label
 * @property string $status
 * @property array<string, mixed>|null $config
 * @property array<string, string>|null $secrets
 * @property string|null $last_check_status
 * @property Carbon|null $last_checked_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ConnectorConnection extends Model
{
    use \App\Shared\Traits\BelongsToCompany;

    /** États d'une connexion (partagés avec la surface plateforme). */
    public const STATUSES = ['draft', 'active', 'error', 'revoked'];

    /** Verdicts du dernier contrôle. */
    public const CHECK_STATUSES = ['validated', 'invalid', 'unreachable', 'unsupported'];

    protected $table = 'connector_connections';

    protected $fillable = [
        'company_id',
        'catalog_key',
        'label',
        'status',
        'config',
        'secrets',
        'last_check_status',
        'last_checked_at',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        // Chiffrement au repos : la colonne est du texte illisible en base.
        'secrets' => 'encrypted:array',
        'last_checked_at' => 'datetime',
    ];

    // Défense en profondeur : une sérialisation accidentelle du modèle
    // n'expose jamais les secrets déchiffrés.
    protected $hidden = ['secrets'];

    /**
     * Masques affichables des secrets (`••••1234`) — jamais la valeur.
     *
     * @param  list<string>  $secretFields  clés secrètes du catalogue
     * @return array<string, array{configured: bool, mask: string|null}>
     */
    public function maskedSecrets(array $secretFields): array
    {
        $masked = [];
        $secrets = $this->secrets ?? [];

        foreach ($secretFields as $field) {
            $value = (string) ($secrets[$field] ?? '');
            $masked[$field] = [
                'configured' => $value !== '',
                'mask' => $value !== '' ? self::maskValue($value) : null,
            ];
        }

        return $masked;
    }

    /**
     * Masque une valeur secrète en conservant un préfixe lisible quand il
     * existe (`sk_live_…4242`) et les 4 derniers caractères.
     */
    public static function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return '••••';
        }

        $tail = substr($value, -4);

        // Préfixe lisible : jusqu'au premier `_` s'il est court (sk_live_,
        // whsec_, pk_test_…), sinon rien.
        $prefix = '';
        $underscore = strpos($value, '_');
        if ($underscore !== false && $underscore <= 12) {
            $prefix = substr($value, 0, $underscore + 1);
        }

        return $prefix.'••••'.$tail;
    }

    /**
     * Représentation API : configuration non secrète + masques, JAMAIS un
     * secret en clair.
     *
     * @param  list<string>  $secretFields
     * @return array<string, mixed>
     */
    public function toApi(array $secretFields): array
    {
        return [
            'id' => $this->id,
            'catalog_key' => $this->catalog_key,
            'label' => $this->label,
            'status' => $this->status,
            'config' => $this->config ?? (object) [],
            'secrets' => $this->maskedSecrets($secretFields),
            'last_check_status' => $this->last_check_status,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
