<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Application\Services\ApplicantStatusJournal;
use App\Modules\Recruitment\Domain\Models\Applicant;

/**
 * Cas d'usage : mise à jour d'une candidature (statut, évaluation, notes).
 *
 * ATS — un changement de statut est journalisé AVANT d'être appliqué : si
 * l'écriture du journal échoue, le statut ne bouge pas. L'inverse laisserait
 * un dossier dans un état que rien ne documente.
 */
class UpdateApplicantAction
{
    public function __construct(
        private readonly ApplicantStatusJournal $journal,
    ) {}

    /**
     * @param  array<string, mixed>  $data  champs validés
     * @param  array{id?: int|null, name?: string|null}  $actor  auteur du changement
     */
    public function execute(Applicant $applicant, array $data, array $actor = []): Applicant
    {
        $previousStatus = $applicant->status;
        $nextStatus = isset($data['status']) && is_string($data['status']) ? $data['status'] : null;

        if ($nextStatus !== null && $nextStatus !== $previousStatus) {
            $this->journal->record(
                $applicant,
                $previousStatus,
                $nextStatus,
                $actor,
                isset($data['status_reason']) && is_string($data['status_reason']) ? $data['status_reason'] : null,
            );
        }

        // `status_reason` n'est pas une colonne : c'est la justification portée
        // par l'événement d'historique, pas un champ du dossier.
        unset($data['status_reason']);

        $applicant->update($data);

        return $applicant->fresh() ?? $applicant;
    }
}
