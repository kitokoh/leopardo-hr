<?php

declare(strict_types=1);

namespace App\Core\Feature\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature flag plateforme (MAT-010, #5868 — BC-01 PLATFORM).
 *
 * Représente un interrupteur de fonctionnalité au niveau plateforme :
 * - dimension `global`   : kill switch du flag pour TOUS les tenants ;
 * - dimension `module`   : kill switch d'un module entier ;
 * - dimension `tenant`   : kill switch pour un tenant donné (id company) ;
 * - dimension `solution` : kill switch pour une solution (fuel_station, edu…) ;
 * - dimension `provider` : kill switch pour un provider (openai, claude…) ;
 * - dimension `version`  : kill switch pour une version d'API/mobile.
 *
 * L'état est fail-closed (`enabled = false` par défaut) et audité via un
 * historique append-only dans la colonne JSONB `history`.
 *
 * @property int $id
 * @property string $flag_key
 * @property string $dimension
 * @property string|null $dimension_value
 * @property bool $enabled
 * @property string|null $reason
 * @property string|null $changed_by
 * @property array<int, array<string, mixed>> $history
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin Builder<static>
 */
class PlatformFeatureFlag extends Model
{
    public const DIMENSIONS = ['global', 'module', 'tenant', 'solution', 'provider', 'version'];

    protected $table = 'platform_feature_flags';

    protected $fillable = [
        'flag_key',
        'dimension',
        'dimension_value',
        'enabled',
        'reason',
        'changed_by',
        'history',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'history' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Ajoute une entrée append-only à l'historique d'audit.
     *
     * @param  array<string, mixed>  $entry
     */
    public function appendHistory(array $entry): void
    {
        $history = is_array($this->history) ? $this->history : [];
        $history[] = [
            'at' => now()->toISOString(),
            'from' => $entry['from'] ?? null,
            'to' => $entry['to'] ?? null,
            'by' => $entry['by'] ?? null,
            'reason' => $entry['reason'] ?? null,
        ];

        // Borne l'historique pour éviter une croissance illimitée (MAT-014).
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $this->history = $history;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForKey(Builder $query, string $flagKey): Builder
    {
        return $query->where('flag_key', $flagKey);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDimension(Builder $query, string $dimension, ?string $value = null): Builder
    {
        return $query
            ->where('dimension', $dimension)
            ->where('dimension_value', $value);
    }
}
