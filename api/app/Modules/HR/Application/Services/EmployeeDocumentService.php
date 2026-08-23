<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Services;

use App\Modules\HR\Domain\Models\EmployeeDocument;
use Illuminate\Support\Collection;

/**
 * Issue #5326 (gap G3 — checklist documents du dossier employé, spec #5258 §5).
 *
 * Matrice des documents requis par étape du cycle de vie et calcul du badge
 * « dossier complet » sur la fiche employé.
 *
 * Note : le module HR orchestre/renseigne la checklist ; il ne génère aucun
 * document lui-même (constitution §III — les PDF paie restent côté Payroll).
 * Les documents « générés » par les autres features (contrat signé, décision
 * de carrière, SdC, attestation) doivent être enregistrés ici par l'acteur
 * RH pour compléter le dossier.
 */
final class EmployeeDocumentService
{
    private function __construct()
    {
    }

    /**
     * Statut employé → types de documents requis pour considérer le dossier
     * complet. `career_decision` et `other` restent optionnels (extras).
     *
     * @return array<string, list<string>>
     */
    public static function requiredTypesPerStatus(): array
    {
        return [
            'pending' => [EmployeeDocument::TYPE_EMPLOYEE_FILE],
            'active' => [
                EmployeeDocument::TYPE_CONTRACT_SIGNED,
                EmployeeDocument::TYPE_EMPLOYEE_FILE,
            ],
            'suspended' => [
                EmployeeDocument::TYPE_CONTRACT_SIGNED,
                EmployeeDocument::TYPE_EMPLOYEE_FILE,
            ],
            'departed' => [
                EmployeeDocument::TYPE_CONTRACT_SIGNED,
                EmployeeDocument::TYPE_EMPLOYEE_FILE,
                EmployeeDocument::TYPE_DEPARTURE_RECORD,
                EmployeeDocument::TYPE_NOTICE_SUMMARY,
                EmployeeDocument::TYPE_SETTLEMENT,
                EmployeeDocument::TYPE_CERTIFICATE,
            ],
            'archived' => [
                EmployeeDocument::TYPE_CONTRACT_SIGNED,
                EmployeeDocument::TYPE_EMPLOYEE_FILE,
                EmployeeDocument::TYPE_DEPARTURE_RECORD,
                EmployeeDocument::TYPE_NOTICE_SUMMARY,
                EmployeeDocument::TYPE_SETTLEMENT,
                EmployeeDocument::TYPE_CERTIFICATE,
            ],
        ];
    }

    /**
     * Badge « dossier complet » : un type requis est satisfait dès qu'une
     * ligne du registre existe pour ce type avec un statut différent de
     * `missing`.
     *
     * @param Collection<int, EmployeeDocument> $documents
     *
     * @return array{
     *     complete: bool,
     *     required: list<string>,
     *     present: list<string>,
     *     missing: list<string>
     * }
     */
    public static function dossierSummary(string $employeeStatus, Collection $documents): array
    {
        $required = self::requiredTypesPerStatus()[$employeeStatus] ?? [EmployeeDocument::TYPE_EMPLOYEE_FILE];

        /** @var list<string> $presentTypes */
        $presentTypes = $documents
            ->filter(static fn (EmployeeDocument $document): bool => $document->status !== EmployeeDocument::STATUS_MISSING)
            ->pluck('type')
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff($required, $presentTypes));
        $present = array_values(array_intersect($required, $presentTypes));

        return [
            'complete' => $missing === [],
            'required' => $required,
            'present' => $present,
            'missing' => $missing,
        ];
    }
}
