<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8685) — instance de fournisseur (CONFIGURATION).
 *
 * C'est ici que vit le deuxieme niveau du design : une ligne = un moyen de
 * paiement disponible, cree par un admin depuis le Superadmin. `type_code`
 * pointe vers un type du CODE (`ProviderTypeRegistry`) — ajouter un moyen de
 * paiement d'un type deja implemente ne demande donc aucune ligne de code.
 *
 * `company_id` nullable : NULL = instance globale, sinon instance dediee a un
 * tenant qui surcharge l'instance globale. Ce modele n'utilise donc PAS le
 * scope tenant automatique (il exclurait les instances globales) : le filtrage
 * est explicite dans `ProviderResolver`.
 *
 * `config` est chiffree at-rest (cast `encrypted:array`) et n'est jamais
 * exposee en clair par l'API de lecture.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $type_code
 * @property string $display_name
 * @property list<string>|null $countries
 * @property list<string>|null $currencies
 * @property ProviderInstanceStatus $status
 * @property array<string, mixed>|null $config
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PaymentProviderInstance extends Model
{
    protected $table = 'payment_provider_instances';

    protected $fillable = [
        'company_id',
        'type_code',
        'display_name',
        'countries',
        'currencies',
        'status',
        'config',
        'priority',
    ];

    protected $casts = [
        'countries' => 'array',
        'currencies' => 'array',
        'status' => ProviderInstanceStatus::class,
        // Chiffrement at-rest : la colonne est du texte illisible en base.
        'config' => 'encrypted:array',
        'priority' => 'integer',
    ];

    /** @return HasMany<PaymentWebhookEndpoint, $this> */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(PaymentWebhookEndpoint::class, 'provider_instance_id');
    }

    public function isResolvable(): bool
    {
        return $this->status->isResolvable();
    }

    /**
     * Le marche est-il couvert ? Une liste vide ou nulle = couverture totale.
     */
    public function coversMarket(string $country, string $currency): bool
    {
        $countries = $this->countries ?? [];
        $currencies = $this->currencies ?? [];

        $countryOk = $countries === [] || in_array(strtoupper($country), array_map('strtoupper', $countries), true);
        $currencyOk = $currencies === [] || in_array(strtoupper($currency), array_map('strtoupper', $currencies), true);

        return $countryOk && $currencyOk;
    }

    /**
     * Vue publique : aucun secret ne sort d'ici.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'type_code' => $this->type_code,
            'display_name' => $this->display_name,
            'countries' => $this->countries ?? [],
            'currencies' => $this->currencies ?? [],
            'status' => $this->status->value,
            'priority' => $this->priority,
            // Masquage systematique : la valeur n'est jamais renvoyee.
            'config' => $this->maskedConfig(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function maskedConfig(): array
    {
        $config = $this->config ?? [];
        $masked = [];

        foreach (array_keys($config) as $key) {
            $masked[(string) $key] = '••••';
        }

        return $masked;
    }
}
