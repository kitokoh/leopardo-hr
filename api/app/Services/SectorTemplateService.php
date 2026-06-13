<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SectorTemplateService
{
    /**
     * @param Company $company
     * @return void
     */
    public function applyTemplate(Company $company): void
    {
        $sector = strtolower($company->sector ?? 'standard');

        $this->seedAbsenceTypes($company->id, $sector);
        $this->seedPositions($company->id, $sector);
    }

    private function seedAbsenceTypes(string $companyId, string $sector): void
    {
        $types = [
            ['company_id' => $companyId, 'name' => 'Congé Annuel', 'code' => 'CA', 'is_paid' => true, 'deducts_leave' => true, 'requires_proof' => false],
            ['company_id' => $companyId, 'name' => 'Maladie', 'code' => 'MAL', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Maternité', 'code' => 'MAT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Paternité', 'code' => 'PAT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            ['company_id' => $companyId, 'name' => 'Congé Sans Solde', 'code' => 'CSS', 'is_paid' => false, 'deducts_leave' => false, 'requires_proof' => false],
        ];

        if ($sector === 'btp' || $sector === 'construction') {
            $types[] = ['company_id' => $companyId, 'name' => 'Intempéries', 'code' => 'INT', 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => false];
            $types[] = ['company_id' => $companyId, 'name' => 'Chômage Technique', 'code' => 'CHOM', 'is_paid' => false, 'deducts_leave' => false, 'requires_proof' => false];
        }

        DB::table($this->tenantTable('absence_types'))->insertOrIgnore($types);
    }

    private function seedPositions(string $companyId, string $sector): void
    {
        // On s'assure d'abord que la table positions existe
        if (! $this->sharedTableExists('positions')) {
            return;
        }

        $positions = [];

        if ($sector === 'btp' || $sector === 'construction') {
            $positions = ['Ouvrier', 'Chef de Chantier', 'Conducteur de Travaux', 'Maçon', 'Électricien'];
        } elseif ($sector === 'sécurité' || $sector === 'securite' || $sector === 'security') {
            $positions = ['Agent de Sécurité', 'Superviseur', 'Rondier', 'Chef de Poste'];
        } else {
            $positions = ['Employé', 'Manager', 'Directeur'];
        }

        $insertData = [];
        foreach ($positions as $position) {
            $insertData[] = [
                'company_id' => $companyId,
                'name' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table($this->tenantTable('positions'))->insertOrIgnore($insertData);
    }

    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }

    private function sharedTableExists(string $table): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable($table);
        }

        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.tables
                where table_schema = ?
                  and table_name = ?
            ) as exists',
            ['shared_tenants', $table]
        );

        return (bool) ($result->exists ?? false);
    }
}
