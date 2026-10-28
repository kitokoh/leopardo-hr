<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\KioskAnnouncementReader;

/**
 * #7962 (suite de #8944) — cas d'usage : annonces affichées sur un kiosque.
 *
 * Extrait de `KioskController::doAnnouncements()`. Le contrôleur ne garde que
 * l'enveloppe HTTP ; la sélection (fenêtre de validité, priorité, plafond) et
 * la tolérance au schéma vivent derrière l'interface.
 *
 * Cette Action est volontairement mince : elle ne fait que nommer le cas
 * d'usage. C'est ce que demande la convention #6570 — le contrôleur orchestre,
 * l'Action expose UN `execute()`, l'Infrastructure parle à la base. Une Action
 * qui dupliquerait la requête créerait deux endroits à corriger le jour où la
 * fenêtre de validité change.
 */
class BuildKioskAnnouncements
{
    public function __construct(private readonly KioskAnnouncementReader $reader) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(Company $company): array
    {
        return $this->reader->forCompany($company);
    }
}
