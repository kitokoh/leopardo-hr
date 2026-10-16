<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Activation d'un outil métier (capacité) pour l'exploitation.
 *
 * ADR-FARM-0001 : c'est ICI que vit la modularité Farm OS (pas dans 13 feature
 * flags). Une ligne par capacité, `enabled` portant la décision de l'utilisateur
 * (acceptée ou refusée à l'étape 3 de l'onboarding, modifiable ensuite).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $capability
 * @property bool $enabled
 * @property string $source
 * @property int|null $decided_by
 * @property Carbon $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmCapabilitySetting extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_capabilities';

    /** Sources d'une décision : onboarding, réglages, système. */
    public const SOURCES = ['onboarding', 'settings', 'system'];

    protected $fillable = [
        'company_id',
        'capability',
        'enabled',
        'source',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enum(): ?FarmCapability
    {
        return FarmCapability::tryFrom($this->capability);
    }

    /**
     * Décisions déclarées par le tenant : capacité ⇒ activée ?.
     *
     * @return array<string, bool>
     */
    public static function decisions(): array
    {
        /** @var array<string, bool> $rows */
        $rows = self::query()->pluck('enabled', 'capability')->all();

        return $rows;
    }
}
