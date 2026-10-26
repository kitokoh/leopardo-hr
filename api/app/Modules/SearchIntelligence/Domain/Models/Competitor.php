<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-7 (#8518) — observation concurrentielle datée et sourcée.
 *
 * `source_url`, `source_label` et `observed_at` sont NOT NULL en base : on ne
 * peut pas enregistrer « le concurrent fait X » sans dire où et quand on l'a
 * lu. C'est la traduction en schéma de la règle produit « sources datées
 * obligatoires ».
 *
 * @property int $id
 * @property string $name
 * @property string|null $website_url
 * @property string|null $market
 * @property string $source_url
 * @property string $source_label
 * @property Carbon $observed_at
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
        'source_url',
        'source_label',
        'observed_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
        ];
    }

    /**
     * Une observation est « fraîche » tant qu'elle a moins de N jours — au-delà
     * elle reste affichée mais marquée comme ancienne, pour ne pas présenter
     * une veille périmée comme actuelle.
     *
     * @param  Builder<self>  $query
     */
    public function scopeFresh(Builder $query, int $days = 90): void
    {
        $query->where('observed_at', '>=', Carbon::now()->subDays($days));
    }
}
