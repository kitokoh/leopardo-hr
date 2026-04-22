<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'sector',
        'country',
        'city',
        'address',
        'email',
        'phone',
        'plan_id',
        'schema_name',
        'tenancy_type',
        'status',
        'subscription_start',
        'subscription_end',
        'language',
        'timezone',
        'currency',
        'notes',
        'features',
        'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'features' => '{}',
        'metadata' => '{}',
    ];

    /**
     * Liste des modules connus de la plateforme (APV L.08).
     * L ajout d un nouveau module passe par cette constante + une entree dans docs/ROADMAP.md.
     */
    public const KNOWN_MODULES = [
        'rh',
        'finance',
        'cameras',
        'muhasebe',
        'leo_ai',
    ];

    /**
     * Indique si un module (ou une sous-feature) est actif pour cette company.
     * Le module RH est actif par defaut (base de l app).
     */
    public function hasFeature(string $key): bool
    {
        $features = $this->features ?? [];

        if ($key === 'rh') {
            return (bool) ($features['rh'] ?? true);
        }

        return (bool) ($features[$key] ?? false);
    }

    /**
     * Toggle explicite d une feature (reserve super-admin / commande console).
     */
    public function setFeature(string $key, bool $enabled): void
    {
        $features = $this->features ?? [];
        $features[$key] = $enabled;
        $this->features = $features;
    }

    protected static function booted(): void
    {
        static::saved(function (self $company): void {
            if (! $company->wasChanged('status')) {
                return;
            }

            if (! in_array($company->status, ['suspended', 'expired'], true)) {
                return;
            }

            Employee::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->get()
                ->each(fn (Employee $employee) => $employee->tokens()->delete());
        });
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    public function biometricRequests(): HasMany
    {
        return $this->hasMany(BiometricEnrollmentRequest::class, 'company_id');
    }

    public function attendanceKiosks(): HasMany
    {
        return $this->hasMany(AttendanceKiosk::class, 'company_id');
    }
}
