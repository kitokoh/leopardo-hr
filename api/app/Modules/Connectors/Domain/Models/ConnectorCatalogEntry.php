<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #9838 (BC-14 INTEGRATION) — entrée du CATALOGUE des connecteurs.
 *
 * Cette table est la raison pour laquelle l'onglet « Connecteurs » est
 * dynamique : le formulaire de saisie n'est pas écrit dans le front, il est
 * DÉCRIT par `field_schema`. Ajouter un type de connecteur (une banque, une
 * marketplace, un CMS) est donc une écriture de données.
 *
 * `audience` borne la visibilité :
 *   - `platform` : Superadmin seulement (les banques de la plateforme) ;
 *   - `tenant`   : espaces clients seulement (la page produit Facebook d'un client) ;
 *   - `both`     : les deux (Stripe, PayPal).
 *
 * Le modèle ne porte AUCUN secret : il décrit la forme d'une saisie, pas son
 * contenu. Les valeurs vivent dans `PlatformConnectorConnection` /
 * `ConnectorConnection`, chiffrées.
 *
 * @property int $id
 * @property string $key
 * @property string $category
 * @property string $audience
 * @property string $label
 * @property string|null $provider
 * @property list<array<string, mixed>>|null $field_schema
 * @property string|null $docs_url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ConnectorCatalogEntry extends Model
{
    /** Audiences acceptées. */
    public const AUDIENCES = ['platform', 'tenant', 'both'];

    /** Types de champ acceptés dans `field_schema`. */
    public const FIELD_TYPES = [
        'text',
        'password',
        'url',
        'email',
        'tel',
        'number',
        'select',
        'textarea',
    ];

    protected $table = 'connector_catalog';

    protected $fillable = [
        'key',
        'category',
        'audience',
        'label',
        'provider',
        'field_schema',
        'docs_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'field_schema' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * L'entrée est-elle proposée à cette audience ?
     *
     * `both` couvre les deux surfaces ; une entrée désactivée n'est proposée
     * nulle part (mais reste lisible pour les connexions existantes).
     */
    public function isAvailableFor(string $audience): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->audience === 'both' || $this->audience === $audience;
    }

    /**
     * Champs du formulaire, normalisés.
     *
     * Une entrée de catalogue sans schéma est légitime (connecteur purement
     * déclaratif) : on rend une liste vide plutôt que `null`, pour que les
     * consommateurs n'aient pas à distinguer les deux cas.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        $schema = $this->field_schema ?? [];

        return array_values(array_filter(
            $schema,
            static fn (mixed $field): bool => is_array($field) && isset($field['key']) && is_string($field['key']),
        ));
    }

    /**
     * Clés des champs SECRETS (chiffrés, jamais renvoyés en clair).
     *
     * @return list<string>
     */
    public function secretFields(): array
    {
        $keys = [];

        foreach ($this->fields() as $field) {
            if (($field['secret'] ?? false) === true) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /**
     * Clés des champs NON secrets (affichables, stockés dans `config`).
     *
     * @return list<string>
     */
    public function publicFields(): array
    {
        $keys = [];

        foreach ($this->fields() as $field) {
            if (($field['secret'] ?? false) !== true) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /**
     * Champs obligatoires.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        $keys = [];

        foreach ($this->fields() as $field) {
            if (($field['required'] ?? false) === true) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /**
     * Représentation API : le schéma complet, pour que le front génère le
     * formulaire sans connaître aucun type de connecteur à l'avance.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category,
            'audience' => $this->audience,
            'label' => $this->label,
            'provider' => $this->provider,
            'docs_url' => $this->docs_url,
            'sort_order' => $this->sort_order,
            'fields' => $this->fields(),
        ];
    }
}
