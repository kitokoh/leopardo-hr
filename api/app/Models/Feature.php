<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $company_id
 * @property string $key
 * @property string $title
 * @property string $description
 * @property string $endpoint
 * @property list<string> $http_methods
 * @property array<string, mixed> $parameters
 * @property array<string, mixed> $response_schema
 * @property list<string> $permissions
 * @property string $mobile_version_min
 * @property string|null $mobile_version_max
 * @property string $api_version
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Feature extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'features';

    protected $fillable = [
        'company_id',
        'key',
        'title',
        'description',
        'endpoint',
        'http_methods',
        'parameters',
        'response_schema',
        'permissions',
        'mobile_version_min',
        'mobile_version_max',
        'api_version',
        'status',
        'metadata',
    ];

    protected $casts = [
        'http_methods' => 'array',
        'parameters' => 'array',
        'response_schema' => 'array',
        'permissions' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Génère le tableau de données pour le manifeste JSON.
     *
     * @return array<string, mixed>
     */
    public function toManifestArray(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'endpoint' => $this->endpoint,
            'methods' => $this->http_methods,
            'parameters' => $this->parameters,
            'response_schema' => $this->response_schema,
            'permissions' => $this->permissions,
            'mobile_version_min' => $this->mobile_version_min,
            'mobile_version_max' => $this->mobile_version_max,
            'ui_type' => $metadata['ui_type'] ?? 'generic',
            'form_schema' => $metadata['form_schema'] ?? null,
            'list_schema' => $metadata['list_schema'] ?? null,
            'status' => $this->status,
            'api_version' => $this->api_version,
        ];
    }

    /**
     * Scope pour récupérer uniquement les fonctionnalités actives.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope pour récupérer les fonctionnalités compatibles avec une version mobile.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCompatibleWith(Builder $query, string $mobileVersion): Builder
    {
        return $query->where('mobile_version_min', '<=', $mobileVersion)
            ->where(function (Builder $query) use ($mobileVersion): void {
                $query->whereNull('mobile_version_max')
                    ->orWhere('mobile_version_max', '>=', $mobileVersion);
            });
    }

    /**
     * Scope pour récupérer les fonctionnalités par version API.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForApiVersion(Builder $query, string $apiVersion): Builder
    {
        return $query->where('api_version', $apiVersion);
    }
}
