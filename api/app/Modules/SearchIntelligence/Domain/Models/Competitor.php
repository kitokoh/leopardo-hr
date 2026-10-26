<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-7 (#8518) — observation concurrentielle datée et sourcée.
 *
 * `source_url`, `source_label` et `observed_at` sont NOT NULL : on ne peut pas
 * enregistrer « le concurrent fait X » sans dire où et quand on l'a lu.
 *
 * Et pour le TARIF, la règle est plus stricte encore : dès qu'il y a une note
 * de tarification, `pricing_sources` doit contenir au moins une source datée
 * (contrainte CHECK de `2026_10_26_000005`). Un prix sans source est une
 * rumeur, et une grille tarifaire fausse est un argument commercial
 * dangereux.
 *
 * @property int $id
 * @property string $name
 * @property string|null $website_url
 * @property string|null $market
 * @property string|null $positioning
 * @property string|null $value_proposition
 * @property array<int, mixed>|null $public_features
 * @property string|null $pricing_notes
 * @property array<int, mixed>|null $pricing_sources
 * @property string $source_url
 * @property string $source_label
 * @property Carbon $observed_at
 * @property Carbon|null $last_reviewed_at
 * @property string|null $notes
 *
 * @mixin Builder<static>
 */
class Competitor extends Model
{
    protected $table = 'competitors';

    protected $fillable = [
        'name',
        'website_url',
        'market',
        'positioning',
        'value_proposition',
        'public_features',
        'pricing_notes',
        'pricing_sources',
        'source_url',
        'source_label',
        'observed_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'public_features' => 'array',
            'pricing_sources' => 'array',
            'observed_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * Une note tarifaire sans source datée serait une affirmation invérifiable.
     * Vérifié avant l'écriture pour produire un 422 explicite ; la contrainte
     * CHECK reste le dernier rempart.
     */
    public function hasSourcedPricing(): bool
    {
        if ($this->pricing_notes === null || trim($this->pricing_notes) === '') {
            return true;
        }

        $sources = $this->pricing_sources;

        if (! is_array($sources) || $sources === []) {
            return false;
        }

        foreach ($sources as $source) {
            if (! is_array($source)) {
                return false;
            }

            // Chaque source doit porter une URL ET une date d'observation.
            if (empty($source['url']) || empty($source['observed_at'])) {
                return false;
            }
        }

        return true;
    }

    /** Acte de revue : met à jour `last_reviewed_at`, jamais `observed_at`. */
    public function markReviewed(?Carbon $at = null): void
    {
        $this->last_reviewed_at = $at ?? Carbon::now();
    }

    /**
     * Une veille est « fraîche » tant qu'elle a moins de N jours — au-delà
     * elle reste affichée mais marquée comme ancienne.
     *
     * @param  Builder<self>  $query
     */
    public function scopeFresh(Builder $query, int $days = 90): void
    {
        $query->where('observed_at', '>=', Carbon::now()->subDays($days));
    }
}
