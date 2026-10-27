<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\KioskDirectoryReader;

/**
 * #8944 (sous-tâche de #7962) — cas d'usage : roster biométrique du kiosque.
 *
 * Extrait de `KioskController::doRoster()` (contrôleur de 980 lignes, cible de
 * l'audit structurel). Le contrôleur ne fait plus que l'orchestration HTTP
 * (résolution du kiosque, search_path, réponse) ; la requête et la
 * sérialisation vivent dans `KioskDirectoryReader` (Infrastructure), car
 * `check-layer-purity` (#6568) interdit les facades Laravel dans Application.
 */
class BuildKioskRoster
{
    public function __construct(private readonly KioskDirectoryReader $reader) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(Company $company): array
    {
        return $this->reader->roster($company);
    }
}
