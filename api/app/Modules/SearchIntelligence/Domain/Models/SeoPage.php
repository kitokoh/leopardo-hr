<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * GSI-2 (#8513) — page observée du parc (une ligne = une URL unique).
 *
 * `url_hash` porte l'unicité : les URL dépassent la taille maximale d'un index
 * btree et deux URL peuvent ne différer que par un paramètre de requête, on
 * indexe donc sha256 de l'URL absolue plutôt que l'URL elle-même.
 *
 * @property int $id
 * @property string $url
 * @property string $url_hash
 * @property string $host
 * @property string $path
 * @property string|null $title
 * @property string|null $meta_description
 * @property int $http_status
 * @property int $word_count
 * @property string|null $canonical_url
 * @property string|null $robots
 * @property bool $has_schema
 * @property bool $is_indexable
 * @property Carbon|null $last_scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class SeoPage extends Model
{
    protected $table = 'seo_pages';

    protected $fillable = [
        'url',
        'url_hash',
        'host',
        'path',
        'title',
        'meta_description',
        'http_status',
        'word_count',
        'canonical_url',
        'robots',
        'has_schema',
        'is_indexable',
        'last_scanned_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'word_count' => 'integer',
            'has_schema' => 'boolean',
            'is_indexable' => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    /** @return HasMany<SeoIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(SeoIssue::class, 'seo_page_id');
    }

    /**
     * Clé d'unicité stable d'une URL : l'URL est normalisée (espaces de bord
     * retirés, fragment supprimé) avant hachage pour que
     * `https://x.fr/a` et `https://x.fr/a#top` soient la même page.
     */
    public static function hashFor(string $url): string
    {
        $normalized = trim($url);
        $hashPosition = mb_strpos($normalized, '#');

        if ($hashPosition !== false) {
            $normalized = mb_substr($normalized, 0, $hashPosition);
        }

        return hash('sha256', $normalized);
    }

    /**
     * Une page saine : 200, indexable, avec un titre non vide. Utilisé par la
     * vue d'ensemble pour compter les pages réellement exploitables.
     *
     * @param  Builder<self>  $query
     */
    public function scopeHealthy(Builder $query): void
    {
        $query->where('http_status', 200)
            ->where('is_indexable', true)
            ->whereNotNull('title');
    }
}
