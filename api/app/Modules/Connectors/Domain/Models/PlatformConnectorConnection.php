<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #9846 (BC-14 INTEGRATION) — connexion de la PLATEFORME à un service externe.
 *
 * Une ligne = un compte externe que l'éditeur utilise lui-même : ses banques
 * (celles qui reçoivent les paiements des tenants), ses comptes PSP, ses
 * comptes de messagerie transactionnelle.
 *
 * C'est la réponse au besoin « connecter une ou plusieurs de nos banques de
 * façon dynamique, au lieu de les saisir en dur » : la configuration bancaire
 * de la plateforme devient une DONNÉE éditable depuis le Superadmin, plus une
 * variable d'environnement qui exige un redéploiement.
 *
 * Schéma PUBLIC, pas de `company_id` : ces connexions appartiennent à
 * l'éditeur. Le modèle n'utilise donc PAS `BelongsToCompany` (le scope global
 * fail-closed exclurait tout, faute de contexte tenant sur la surface
 * plateforme).
 *
 * EXCEPTION TENANT-SCOPE (#7999) — déclarée dans
 * dev-hub/governance/tenant-scope-exceptions.json. La garde
 * `check-tenant-scope-models.sh` cherche le mot `company_id` par grep
 * textuel : elle le trouve dans cette phrase même, qui documente son
 * ABSENCE. Le marqueur ci-dessus est ce qui la fait sortir en exception
 * justifiée plutôt qu'en violation.
 *
 * SÉCURITÉ : `secrets` chiffré au repos (cast `encrypted:array`) + `$hidden` ;
 * l'API ne renvoie que des masques, en écriture seule.
 *
 * @property int $id
 * @property string $catalog_key
 * @property string $label
 * @property string $status
 * @property array<string, mixed>|null $config
 * @property array<string, string>|null $secrets
 * @property string|null $last_check_status
 * @property Carbon|null $last_checked_at
 * @property bool $is_default
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PlatformConnectorConnection extends Model
{
    /** États d'une connexion (partagés avec la surface tenant). */
    public const STATUSES = ['draft', 'active', 'error', 'revoked'];

    /** Verdicts du dernier contrôle. */
    public const CHECK_STATUSES = ['validated', 'invalid', 'unreachable', 'unsupported'];

    protected $table = 'platform_connector_connections';

    protected $fillable = [
        'catalog_key',
        'label',
        'status',
        'config',
        'secrets',
        'last_check_status',
        'last_checked_at',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        // Chiffrement au repos : la colonne est du texte illisible en base.
        'secrets' => 'encrypted:array',
        'last_checked_at' => 'datetime',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['secrets'];

    /**
     * Masques affichables des secrets — jamais la valeur.
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
                'mask' => $value !== '' ? ConnectorConnection::maskValue($value) : null,
            ];
        }

        return $masked;
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
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
