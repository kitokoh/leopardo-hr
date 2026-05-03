<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $company_id
 * @property string $key
 * @property string $title
 * @property string $description
 * @property string $endpoint
 * @property array $http_methods
 * @property array $parameters
 * @property array $response_schema
 * @property array $permissions
 * @property string $mobile_version_min
 * @property string|null $mobile_version_max
 * @property string $api_version
 * @property string $status
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Feature query()
 * @method static \Illuminate\Database\Eloquent\Builder|Feature where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Feature active()
 * @method static \Illuminate\Database\Eloquent\Builder|Feature forApiVersion(string $apiVersion)
 * @method static \Illuminate\Database\Eloquent\Builder|Feature compatibleWith(string $mobileVersion)
 * @method static \Illuminate\Database\Eloquent\Builder|Feature orderBy($column, $direction = 'asc')
 * @method static int count($columns = '*')
 * @method static mixed min($column)
 * @method static \Illuminate\Database\Eloquent\Builder|Feature select($columns = ['*'], ...$args)
 * @method static \Illuminate\Support\Collection<int, string> pluck($column, $key = null)
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
     * Génère le tableau de données pour le manifeste JSON
     */
    public function toManifestArray(): array
    {
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
            'ui_type' => $this->metadata['ui_type'] ?? 'generic',
            'form_schema' => $this->metadata['form_schema'] ?? null,
            'list_schema' => $this->metadata['list_schema'] ?? null,
            'status' => $this->status,
            'api_version' => $this->api_version,
        ];
    }

    /**
     * Scope pour récupérer uniquement les fonctionnalités actives
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope pour récupérer les fonctionnalités compatibles avec une version mobile
     */
    public function scopeCompatibleWith($query, string $mobileVersion)
    {
        return $query->where('mobile_version_min', '<=', $mobileVersion)
            ->where(function ($q) use ($mobileVersion) {
                $q->whereNull('mobile_version_max')
                    ->orWhere('mobile_version_max', '>=', $mobileVersion);
            });
    }

    /**
     * Scope pour récupérer les fonctionnalités par version API
     */
    public function scopeForApiVersion($query, string $apiVersion)
    {
        return $query->where('api_version', $apiVersion);
    }
}
