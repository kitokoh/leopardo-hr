<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;

/**
 * Seed du référentiel de spécialités médicales standards — HC-002 (#7786).
 *
 * Exécuté à l'activation de la solution (`SolutionActivated`, pattern
 * TravelGeoSeederService #6015) DANS le contexte du tenant.
 *
 * Idempotence : `insertOrIgnore` sur UNIQUE(company_id, code) — rejouer le
 * seed ne crée jamais de doublon et ne réécrit jamais les modifications
 * apportées par le tenant (renommages, désactivations, ajouts).
 */
final class HealthSpecialtySeederService
{
    /**
     * Spécialités standards (code => libellé) — référentiel de départ,
     * éditable ensuite par la direction via l'API.
     *
     * @var array<string, string>
     */
    private const SPECIALTIES = [
        'general_medicine' => 'Médecine générale',
        'cardiology' => 'Cardiologie',
        'pediatrics' => 'Pédiatrie',
        'gynecology_obstetrics' => 'Gynécologie-obstétrique',
        'emergency_medicine' => 'Médecine d’urgence',
        'general_surgery' => 'Chirurgie générale',
        'orthopedics' => 'Orthopédie',
        'anesthesiology' => 'Anesthésie-réanimation',
        'radiology' => 'Radiologie',
        'dermatology' => 'Dermatologie',
        'ophthalmology' => 'Ophtalmologie',
        'ent' => 'Oto-rhino-laryngologie (ORL)',
        'neurology' => 'Neurologie',
        'psychiatry' => 'Psychiatrie',
        'oncology' => 'Oncologie',
        'nephrology' => 'Néphrologie',
        'gastroenterology' => 'Gastro-entérologie',
        'pneumology' => 'Pneumologie',
        'endocrinology' => 'Endocrinologie',
        'laboratory_medicine' => 'Biologie médicale',
    ];

    public function __construct(private readonly TenantManager $tenants) {}

    public function seed(Company $company): void
    {
        $this->tenants->withinTenant($company, function (): void {
            $rows = [];
            foreach (self::SPECIALTIES as $code => $name) {
                $rows[] = [
                    'company_id' => currentCompany()->id,
                    'code' => $code,
                    'name' => $name,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('health_specialties')->insertOrIgnore($rows);
        });
    }
}
