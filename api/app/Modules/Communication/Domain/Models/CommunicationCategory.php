<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Catégorie de la taxonomie email du tenant (BC-29 COMMUNICATION, R3 #7688).
 *
 * Les défauts (`config('communication.classification.default_categories')`)
 * sont matérialisés paresseusement (`is_system = true`, `label` null) ; le
 * libellé affiché retombe alors sur la clé i18n
 * `communication.category_<key>` (FR/EN/AR/TR). Le tenant peut renommer,
 * désactiver ou ajouter ses propres catégories — la sortie du LLM est
 * validée contre les clés ACTIVES uniquement.
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $key
 * @property string|null $label
 * @property bool $is_system
 * @property bool $active
 *
 * @mixin Builder<static>
 */
class CommunicationCategory extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'communication_categories';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'label',
        'is_system',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Libellé affiché : surcharge tenant sinon défaut i18n 4 langues.
     */
    public function displayLabel(): string
    {
        if (is_string($this->label) && trim($this->label) !== '') {
            return $this->label;
        }

        $key = 'communication.category_'.$this->key;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $this->key;
    }
}
