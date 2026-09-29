<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Modules\HR\Domain\Models\Department;
use App\Shared\Contracts\HR\DepartmentDirectory;
use Illuminate\Support\Collection;

/**
 * Adapter du contrat partagé `DepartmentDirectory` (#8211, BOS-023 cycle 3).
 *
 * Le module HR est propriétaire du modèle `Department` : toute lecture
 * cross-module passe par ce contrat au lieu d'importer le modèle directement
 * (règle d'isolation #5584).
 *
 * La requête reprend à l'identique celle qu'`AttendanceReportService`
 * (Attendance) exécutait historiquement : filtre `company_id`, projection
 * `pluck('name', 'id')` — comportement strictement préservé.
 */
final class DepartmentDirectoryAdapter implements DepartmentDirectory
{
    public function namesByCompany(string $companyId): Collection
    {
        return Department::query()
            ->where('company_id', $companyId)
            ->pluck('name', 'id');
    }
}
