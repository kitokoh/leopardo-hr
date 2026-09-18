<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Onboarding\Application\Actions\CompleteSetupInterview;
use App\Modules\Onboarding\Domain\Services\SetupInterviewPlanner;
use App\Modules\Onboarding\Infrastructure\Services\CompanyOnboardingCompletionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #7493 — entretien de préparation conversationnel (première connexion).
 *
 * Le parcours front pose une question à la fois (zappable, reprenable) ; ce
 * contrôleur porte le brouillon SERVEUR (reprise sur un autre appareil) et la
 * clôture qui active les modules selon les réponses.
 *
 *  - GET    /setup-interview          → état (statut, réponses, activations) ;
 *  - PATCH  /setup-interview/answers  → enregistrement incrémental (brouillon) ;
 *  - POST   /setup-interview/complete → activations + statut `completed` (idempotent) ;
 *  - POST   /setup-interview/dismiss  → « terminer plus tard » (idempotent).
 *
 * État persisté dans `public.companies.metadata.setup_interview` (même canal
 * que `welcome_seen_at`/`onboarding_completed`), exposé au portail par
 * `/auth/me` (`company.metadata`) — pas de table dédiée : l'état est petit,
 * borné (allowlist de questions) et vit avec la société.
 *
 * RBAC : responsable du tenant (`principal`/`rh`), miroir de
 * `WelcomeScreenController` — un employé ne configure pas l'espace.
 */
class SetupInterviewController extends Controller
{
    private const STATUSES = ['not_started', 'in_progress', 'completed', 'dismissed'];

    public function __construct(
        private readonly SetupInterviewPlanner $planner,
        private readonly CompleteSetupInterview $completeInterview,
        private readonly CompanyOnboardingCompletionWriter $writer,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        return response()->json(['data' => $this->state($this->freshCompany())]);
    }

    /**
     * Brouillon incrémental : chaque réponse est fusionnée dans l'état
     * serveur (rejouable). `null` = question explicitement sautée. Les clés
     * hors allowlist sont refusées (422, fail-closed, aucune écriture).
     */
    public function saveAnswers(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $raw = $request->input('answers');
        if (! is_array($raw) || $raw === []) {
            throw ValidationException::withMessages([
                'answers' => [__('validation.required', ['attribute' => 'answers'])],
            ]);
        }

        $sanitized = $this->planner->sanitize($raw);
        if ($sanitized['rejected'] !== []) {
            throw ValidationException::withMessages([
                // #7630 (PA2-I18N-007) — message via le catalogue __() au lieu
                // d'une chaîne française en dur.
                'answers' => array_map(
                    static fn (string $question): string => (string) __('onboarding.interview_invalid_answer', ['question' => $question]),
                    $sanitized['rejected']
                ),
            ]);
        }

        $company = $this->freshCompany();
        $metadata = $company->metadata ?? [];
        $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];

        // Un entretien clôturé ne se rouvre pas par un brouillon tardif
        // (idempotence du récapitulatif) : l'état est renvoyé tel quel.
        if (($interview['status'] ?? null) === 'completed') {
            return response()->json(['data' => $this->state($company)]);
        }

        $answers = is_array($interview['answers'] ?? null) ? $interview['answers'] : [];
        $interview['answers'] = array_merge($answers, $sanitized['answers']);
        $interview['status'] = 'in_progress';
        $interview['updated_at'] = now()->toIso8601String();
        $metadata['setup_interview'] = $interview;

        $this->writer->persist((string) $company->id, $metadata);

        return response()->json(['data' => $this->state($this->freshCompany())]);
    }

    /**
     * Clôture : activation des modules selon les réponses (idempotent —
     * rejouer `complete` est un no-op prouvé par test).
     */
    public function complete(Request $request): JsonResponse
    {
        $actor = $this->authorizeManager($request);

        $result = $this->completeInterview->execute($this->freshCompany(), $actor->id);

        return response()->json(['data' => $result]);
    }

    /**
     * « Terminer plus tard » : relance douce depuis le dashboard, jamais
     * bloquante. Idempotent ; sans effet sur un entretien déjà complété.
     */
    public function dismiss(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $company = $this->freshCompany();
        $metadata = $company->metadata ?? [];
        $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];

        if (! in_array($interview['status'] ?? null, ['completed', 'dismissed'], true)) {
            $interview['status'] = 'dismissed';
            $interview['dismissed_at'] = now()->toIso8601String();
            $metadata['setup_interview'] = $interview;
            $this->writer->persist((string) $company->id, $metadata);
        }

        return response()->json(['data' => $this->state($this->freshCompany())]);
    }

    private function authorizeManager(Request $request): Employee
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        abort_if(! $actor instanceof Employee, 401);
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        return $actor;
    }

    /**
     * État normalisé exposé au front (shape stable, statut allowlisté).
     *
     * @return array<string, mixed>
     */
    private function state(Company $company): array
    {
        $metadata = $company->metadata ?? [];
        $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];

        $status = $interview['status'] ?? 'not_started';
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'not_started';
        }

        $activated = is_array($interview['activated'] ?? null) ? $interview['activated'] : [];

        return [
            'status' => $status,
            'answers' => is_array($interview['answers'] ?? null) ? (object) $interview['answers'] : (object) [],
            'questions' => SetupInterviewPlanner::QUESTIONS,
            'completed_at' => $interview['completed_at'] ?? null,
            'dismissed_at' => $interview['dismissed_at'] ?? null,
            'activated' => [
                'solutions' => array_values((array) ($activated['solutions'] ?? [])),
                'tools' => array_values((array) ($activated['tools'] ?? [])),
                'failed' => array_values((array) ($activated['failed'] ?? [])),
            ],
        ];
    }

    /**
     * Relit la société courante depuis la table qualifiée — le modèle résolu
     * par `search_path` (contexte tenant) pointerait vers le mauvais schéma
     * (piège documenté #7322 / `CompanyBrandingController`).
     */
    private function freshCompany(): Company
    {
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
