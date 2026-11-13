<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection d'une candidature pour le back-office recruteur.
 *
 * ATS — `status` est le statut réel du pipeline (`new`, `screening`, …). La
 * ressource exposait auparavant `stage`, un attribut qui n'existe ni en base ni
 * sur le modèle : la clé sortait donc toujours `null`, et l'écran recruteur ne
 * pouvait pas afficher l'étape d'un dossier. On expose `status` (la vérité) et
 * on garde `stage` comme alias explicite pour les clients déjà branchés dessus.
 *
 * `resume_url` n'est renseigné que si le fichier est réellement présent : une
 * URL fabriquée vers un CV absent ferait échouer le téléchargement chez le
 * recruteur sans explication.
 *
 * @mixin Applicant
 */
class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_posting_id' => $this->job_posting_id,
            'job_posting' => $this->whenLoaded('jobPosting', fn () => [
                'id' => $this->jobPosting?->id,
                'title' => $this->jobPosting?->title,
            ]),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'status' => $this->status,
            // Alias de compatibilité : même valeur que `status`.
            'stage' => $this->status,
            'rating' => $this->rating,
            'notes' => $this->notes,
            'cover_letter' => $this->cover_letter,
            'has_resume' => $this->resume_path !== null && $this->resume_path !== '',
            'resume_url' => $this->resumeUrl(),
            'resume_scan_status' => $this->resume_scan_status,
            'resume_scanned_at' => $this->resume_scanned_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'retention_until' => $this->retention_until?->toIso8601String(),
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'status_history' => $this->whenLoaded('statusEvents', fn () => $this->statusEvents->map(fn ($event) => [
                'id' => $event->id,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'actor_id' => $event->actor_id,
                'actor_name' => $event->actor_name,
                'reason' => $event->reason,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * URL de téléchargement du CV, ou `null` s'il n'y en a pas.
     *
     * Le fichier vit sur le disque `local` (privé) : on ne peut pas exposer un
     * chemin de stockage, il faut une route signée. Tant que cette route n'est
     * pas sollicitée, on renvoie `null` plutôt qu'un lien mort.
     */
    private function resumeUrl(): ?string
    {
        if ($this->resume_path === null || $this->resume_path === '') {
            return null;
        }

        return url("/api/v1/recruitment/applicants/{$this->id}/resume");
    }
}
