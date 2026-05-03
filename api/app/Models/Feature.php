<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * GÃ©nÃ¨re le tableau de donnÃ©es pour le manifeste JSON.
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
     * Scope pour rÃ©cupÃ©rer uniquement les fonctionnalitÃ©s actives
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope pour rÃ©cupÃ©rer les fonctionnalitÃ©s compatibles avec une version mobile
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
     * Scope pour rÃ©cupÃ©rer les fonctionnalitÃ©s par version API
     */
    public function scopeForApiVersion($query, string $apiVersion)
    {
        return $query->where('api_version', $apiVersion);
    }
}
