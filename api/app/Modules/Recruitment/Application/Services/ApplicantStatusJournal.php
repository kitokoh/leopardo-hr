<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Services;

use App\Modules\Recruitment\Domain\Models\Applicant;
use App\Modules\Recruitment\Domain\Models\ApplicantStatusEvent;
use Illuminate\Support\Carbon;

/**
 * Écriture et lecture du journal de statuts d'une candidature.
 *
 * Le journal est la seule source de vérité sur « qui a fait quoi, quand ».
 * `Applicant::status` reste la valeur courante (lue par les listes), mais elle
 * ne dit rien de l'historique — d'où cette table séparée, append-only.
 */
final class ApplicantStatusJournal
{
    /**
     * Enregistre un changement de statut.
     *
     * Ne fait RIEN si le statut ne change pas : un enregistrement « new → new »
     * polluerait l'historique et ferait croire à une action du recruteur.
     *
     * @param  array{id?: int|null, name?: string|null}  $actor
     */
    public function record(
        Applicant $applicant,
        ?string $fromStatus,
        string $toStatus,
        array $actor = [],
        ?string $reason = null,
        ?Carbon $occurredAt = null,
    ): ?ApplicantStatusEvent {
        if ($fromStatus === $toStatus) {
            return null;
        }

        return ApplicantStatusEvent::create([
            'company_id' => $applicant->company_id,
            'applicant_id' => $applicant->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? null,
            'reason' => $reason,
            'occurred_at' => $occurredAt ?? Carbon::now(),
        ]);
    }

    /**
     * Entrée initiale d'un dossier (dépôt de candidature).
     *
     * `from_status` est `null` : il n'y a pas d'état antérieur. C'est ce qui
     * distingue « le dossier est né » de « quelqu'un l'a déplacé ».
     *
     * @param  array{id?: int|null, name?: string|null}  $actor
     */
    public function recordCreation(Applicant $applicant, array $actor = []): ApplicantStatusEvent
    {
        return ApplicantStatusEvent::create([
            'company_id' => $applicant->company_id,
            'applicant_id' => $applicant->id,
            'from_status' => null,
            'to_status' => $applicant->status,
            'actor_id' => $actor['id'] ?? null,
            'actor_name' => $actor['name'] ?? null,
            'reason' => null,
            'occurred_at' => $applicant->applied_at ?? Carbon::now(),
        ]);
    }

    /**
     * Historique d'un dossier, du plus récent au plus ancien.
     *
     * @return \Illuminate\Support\Collection<int, ApplicantStatusEvent>
     */
    public function history(Applicant $applicant): \Illuminate\Support\Collection
    {
        return ApplicantStatusEvent::query()
            ->where('company_id', $applicant->company_id)
            ->where('applicant_id', $applicant->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Durée passée dans chaque étape, en heures.
     *
     * Calculée à partir des horodatages réels du journal : c'est la mesure qui
     * permet de dire « nos candidats attendent 9 jours en screening » au lieu
     * de le supposer.
     *
     * @return array<string, float>
     */
    public function timeInStage(Applicant $applicant): array
    {
        $events = $this->history($applicant)->reverse()->values();
        $durations = [];

        foreach ($events as $index => $event) {
            $next = $events->get($index + 1);
            if ($next === null) {
                break;
            }

            $hours = $event->occurred_at->diffInMinutes($next->occurred_at) / 60;
            $durations[$event->to_status] = round(($durations[$event->to_status] ?? 0) + $hours, 2);
        }

        return $durations;
    }
}
