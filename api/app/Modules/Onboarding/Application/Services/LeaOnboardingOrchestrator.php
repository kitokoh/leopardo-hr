<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Application\Actions\CompleteSetupInterview;
use App\Modules\Onboarding\Domain\Exceptions\LeaInterpretationUnavailableException;
use App\Modules\Onboarding\Domain\Services\InterviewAnswerExtractor;
use App\Modules\Onboarding\Domain\Services\SetupInterviewPlanner;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * #8230 (BOS-042) — orchestration du parcours Lea : description libre →
 * interpretation → clarification eventuelle → plan → **confirmation
 * explicite** → execution par le chemin existant.
 *
 * ## L'invariant qui compte : aucun nouveau chemin d'execution
 *
 * L'activation passe par `CompleteSetupInterview` et rien d'autre. Un
 * orchestrateur qui activerait lui-meme les solutions pourrait diverger du
 * parcours d'entretien (ordre, idempotence, audit, plancher solo) — et la
 * parite entre les deux parcours, exigee par le critere d'acceptation,
 * deviendrait impossible a garantir. Ici la parite est **structurelle** :
 * les deux parcours appellent la meme action.
 *
 * ## Confirmation obligatoire, et non seulement « presente »
 *
 * Le plan n'est jamais execute dans le meme appel que l'interpretation. Un
 * `confirm` sans `plan_token` est refuse : sans ce jeton, un appelant
 * pourrait executer un plan que l'utilisateur n'a jamais vu (ou un plan
 * perime apres une clarification). Le jeton est lie au contenu du plan —
 * si les reponses changent, il ne correspond plus.
 *
 * ## Jamais bloquant
 *
 * LLM indisponible, JSON invalide, injection, description vide : tous les
 * cas produisent `fallback_required` avec un code stable, jamais une erreur
 * qui laisserait l'utilisateur sans parcours. L'entretien deterministe reste
 * toujours disponible.
 *
 * ## Pourquoi un service et non une Application Action
 *
 * La convention du depot est stricte : `*Actions*` = **une** methode
 * `execute()` (#6570), verifiee par `check-actions-convention.sh`. Cet
 * orchestrateur expose trois etapes distinctes (`interpret`, `clarify`,
 * `confirm`) parce qu'elles correspondent a trois appels HTTP et que les
 * fusionner rendrait impossible de garantir qu'aucune activation n'a lieu
 * avant confirmation. C'est donc un service de domaine, pas une action —
 * et il delegue l'unique effet de bord a `CompleteSetupInterview`, qui reste
 * la seule action.
 */
final class LeaOnboardingOrchestrator
{
    public const OUTCOME_PLAN_READY = 'plan_ready';

    public const OUTCOME_CLARIFICATION = 'clarification';

    public const OUTCOME_FALLBACK = 'fallback_required';

    public function __construct(
        private readonly InterviewAnswerExtractor $extractor,
        private readonly SetupInterviewPlanner $planner,
        private readonly CompleteSetupInterview $completeInterview,
        private readonly LoggerInterface $logger,
        // #8232 (BOS-044) — mesure du pilote. Les logs `lea.intent.*` restent
        // la trace de debogage ; ce recorder ecrit les MEMES enumerations dans
        // une table interrogeable en SQL, sans jamais stocker de texte libre.
        private readonly LeaPilotMetricsRecorder $metrics,
    ) {}

    /**
     * Etape 1 — interprete la description et propose un plan SANS rien
     * executer.
     *
     * @return array{
     *     outcome: string,
     *     answers?: array<string, mixed>,
     *     plan?: array{solutions: list<string>, tools: list<string>},
     *     plan_token?: string,
     *     questions?: list<string>,
     *     reason_code?: string
     * }
     */
    public function interpret(Company $company, string $description, string $correlationId): array
    {
        try {
            $interpretation = $this->extractor->extract($description, $correlationId);
        } catch (LeaInterpretationUnavailableException $exception) {
            $this->logger->info('lea.intent.rejected', [
                'correlation_id' => $correlationId,
                'company_id' => $company->id,
                'reason_code' => $exception->reasonCode,
            ]);

            $this->metrics->record(
                (string) $company->id,
                LeaPilotMetricsRecorder::STAGE_INTERPRET,
                self::OUTCOME_FALLBACK,
                $exception->reasonCode,
                $correlationId,
            );

            return $this->fallback($exception->reasonCode);
        }

        if ($interpretation->isUnavailable()) {
            $this->logger->info('lea.intent.unavailable', [
                'correlation_id' => $correlationId,
                'company_id' => $company->id,
                'reason_code' => $interpretation->reasonCode,
            ]);

            $this->metrics->record(
                (string) $company->id,
                LeaPilotMetricsRecorder::STAGE_INTERPRET,
                self::OUTCOME_FALLBACK,
                $interpretation->reasonCode ?? 'llm_unavailable',
                $correlationId,
            );

            return $this->fallback($interpretation->reasonCode ?? 'llm_unavailable');
        }

        if ($interpretation->needsClarification()) {
            $this->logger->info('lea.intent.clarification', [
                'correlation_id' => $correlationId,
                'company_id' => $company->id,
                'questions' => $interpretation->clarificationQuestions,
            ]);

            $this->metrics->record(
                (string) $company->id,
                LeaPilotMetricsRecorder::STAGE_INTERPRET,
                self::OUTCOME_CLARIFICATION,
                null,
                $correlationId,
                ['clarification_rounds' => 1],
            );

            return [
                'outcome' => self::OUTCOME_CLARIFICATION,
                'questions' => $interpretation->clarificationQuestions,
            ];
        }

        $answers = $this->withClarificationAnswers($interpretation->answers, []);
        $plan = $this->planner->plan($answers);

        $this->logger->info('lea.intent.plan_ready', [
            'correlation_id' => $correlationId,
            'company_id' => $company->id,
            // Le plan seul est journalise : jamais la description, jamais les
            // valeurs brutes du modele.
            'solutions_count' => count($plan['solutions']),
            'tools_count' => count($plan['tools']),
            'rejected_count' => count($interpretation->rejected),
        ]);

        $this->metrics->record(
            (string) $company->id,
            LeaPilotMetricsRecorder::STAGE_INTERPRET,
            self::OUTCOME_PLAN_READY,
            null,
            $correlationId,
        );

        return [
            'outcome' => self::OUTCOME_PLAN_READY,
            'answers' => $answers,
            'plan' => [
                'solutions' => $plan['solutions'],
                'tools' => $plan['tools'],
            ],
            'plan_token' => $this->planToken($answers),
        ];
    }

    /**
     * Etape 2 — levee d'une clarification : les reponses fournies par
     * l'utilisateur sont fusionnees avec celles deja interpretees, puis
     * re-planifiees. Aucune nouvelle interpretation LLM : l'utilisateur a
     * repondu explicitement, sa reponse prime sur toute deduction.
     *
     * @param  array<string, mixed>  $extracted  Reponses deja validees.
     * @param  array<string, mixed>  $clarified  Reponses de l'utilisateur.
     * @return array{
     *     outcome: string,
     *     answers?: array<string, mixed>,
     *     plan?: array{solutions: list<string>, tools: list<string>},
     *     plan_token?: string,
     *     questions?: list<string>,
     *     reason_code?: string
     * }
     */
    public function clarify(Company $company, array $extracted, array $clarified, string $correlationId): array
    {
        $sanitized = $this->planner->sanitize($clarified);

        if ($sanitized['rejected'] !== []) {
            // Une reponse hors allowlist dans la clarification est renvoyee
            // a l'utilisateur : on ne l'ignore pas, sinon il croirait avoir
            // repondu alors que sa valeur n'a pas ete retenue.
            return [
                'outcome' => self::OUTCOME_CLARIFICATION,
                'questions' => $sanitized['rejected'],
            ];
        }

        $answers = $this->withClarificationAnswers($extracted, $sanitized['answers']);
        $plan = $this->planner->plan($answers);

        $this->logger->info('lea.intent.clarified', [
            'correlation_id' => $correlationId,
            'company_id' => $company->id,
            'solutions_count' => count($plan['solutions']),
        ]);

        $this->metrics->record(
            (string) $company->id,
            LeaPilotMetricsRecorder::STAGE_CLARIFY,
            self::OUTCOME_PLAN_READY,
            null,
            $correlationId,
            ['clarification_rounds' => 1],
        );

        return [
            'outcome' => self::OUTCOME_PLAN_READY,
            'answers' => $answers,
            'plan' => [
                'solutions' => $plan['solutions'],
                'tools' => $plan['tools'],
            ],
            'plan_token' => $this->planToken($answers),
        ];
    }

    /**
     * Etape 3 — confirmation explicite. Le jeton doit correspondre aux
     * reponses presentees ; sinon on refuse, car le plan execute ne serait
     * pas celui que l'utilisateur a vu.
     *
     * @param  array<string, mixed>  $answers
     * @return array{outcome: string, result?: array<string, mixed>, reason_code?: string}
     */
    public function confirm(Company $company, array $answers, string $planToken, ?int $actorId, string $correlationId): array
    {
        if (! hash_equals($this->planToken($answers), $planToken)) {
            $this->logger->warning('lea.intent.stale_confirmation', [
                'correlation_id' => $correlationId,
                'company_id' => $company->id,
            ]);

            $this->metrics->record(
                (string) $company->id,
                LeaPilotMetricsRecorder::STAGE_CONFIRM,
                'stale_confirmation',
                'plan_token_mismatch',
                $correlationId,
            );

            return [
                'outcome' => self::OUTCOME_FALLBACK,
                'reason_code' => 'plan_token_mismatch',
            ];
        }

        // Les reponses confirmees sont persistees dans le brouillon serveur :
        // le recapitulatif de l'entretien doit refleter ce que Lea a compris,
        // sinon un utilisateur qui rouvre l'entretien verrait un autre plan.
        $this->persistAnswers($company, $answers);

        // Chemin d'execution UNIQUE : l'action de l'entretien deterministe.
        $result = $this->completeInterview->execute($company, $actorId);

        $this->logger->info('lea.intent.confirmed', [
            'correlation_id' => $correlationId,
            'company_id' => $company->id,
            'already_completed' => $result['already_completed'],
        ]);

        // #8232 — `answers_modified` mesure le « taux de modification du
        // resume » : on compare les reponses confirmees a celles que le plan
        // proposait. Un ecart signale que Lea a mal compris, pas que
        // l'utilisateur a change d'avis — c'est exactement le signal produit
        // que le pilote doit remonter.
        $this->metrics->record(
            (string) $company->id,
            LeaPilotMetricsRecorder::STAGE_CONFIRM,
            'completed',
            null,
            $correlationId,
            ['answers_modified' => $this->answersDifferFromDraft($company, $answers)],
        );

        return [
            'outcome' => 'completed',
            'result' => $result,
        ];
    }

    /**
     * @return array{outcome: string, reason_code: string}
     */
    private function fallback(string $reasonCode): array
    {
        return [
            'outcome' => self::OUTCOME_FALLBACK,
            'reason_code' => $reasonCode,
        ];
    }

    /**
     * Fusionne les reponses de l'utilisateur **par-dessus** celles deduites
     * du texte : une reponse explicite ne doit jamais etre ecrasee par une
     * deduction.
     *
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $clarified
     * @return array<string, mixed>
     */
    private function withClarificationAnswers(array $extracted, array $clarified): array
    {
        $answers = $extracted;

        foreach ($clarified as $question => $value) {
            $answers[$question] = $value;
        }

        return $answers;
    }

    /**
     * Jeton derive du CONTENU du plan : si les reponses changent apres une
     * clarification, le jeton precedent ne correspond plus. Ce n'est pas un
     * secret, c'est une empreinte d'integrite — d'ou `hash_equals` plus loin,
     * qui garde la comparaison a temps constant.
     *
     * @param  array<string, mixed>  $answers
     */
    private function planToken(array $answers): string
    {
        ksort($answers);

        return substr(hash('sha256', json_encode($answers, JSON_THROW_ON_ERROR)), 0, 32);
    }

    /**
     * #8232 — les reponses confirmees different-elles du brouillon propose ?
     *
     * Mesure le « taux de modification du resume » du pilote. On compare aux
     * reponses DEJA persistees (celles que le plan proposait), pas a une
     * copie en memoire : c'est ce que l'utilisateur a reellement vu a l'ecran.
     *
     * Tolerant par construction : un entretien absent ou illisible renvoie
     * `false` (pas de modification constatee) plutot que de faire echouer la
     * confirmation pour une mesure.
     *
     * @param  array<string, mixed>  $answers
     */
    private function answersDifferFromDraft(Company $company, array $answers): bool
    {
        try {
            $fresh = Company::query()->find($company->id);
            $source = $fresh instanceof Company ? $fresh : $company;

            $metadata = $source->metadata ?? [];
            $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];
            $draft = is_array($interview['answers'] ?? null) ? $interview['answers'] : [];

            if ($draft === []) {
                return false;
            }

            // Comparaison sur les cles du brouillon : une reponse ajoutee par
            // l'utilisateur compte comme une modification, une cle absente du
            // brouillon ne peut pas en etre une.
            foreach ($draft as $question => $value) {
                if (! array_key_exists($question, $answers)) {
                    return true;
                }

                if ($answers[$question] !== $value) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Persiste les reponses dans `companies.metadata.setup_interview` avant
     * execution, pour que le recapitulatif reflete le plan confirme.
     *
     * @param  array<string, mixed>  $answers
     */
    private function persistAnswers(Company $company, array $answers): void
    {
        try {
            $fresh = Company::query()->find($company->id);
            $source = $fresh instanceof Company ? $fresh : $company;

            $metadata = $source->metadata ?? [];
            $interview = is_array($metadata['setup_interview'] ?? null) ? $metadata['setup_interview'] : [];

            // Un entretien deja cloture n'est pas rouvert : l'idempotence de
            // `CompleteSetupInterview` doit rester la seule autorite.
            if (($interview['status'] ?? null) === 'completed') {
                return;
            }

            $existing = is_array($interview['answers'] ?? null) ? $interview['answers'] : [];
            $interview['answers'] = array_merge($existing, $answers);
            $interview['status'] = 'in_progress';
            $interview['updated_at'] = now()->toIso8601String();
            $metadata['setup_interview'] = $interview;

            $source->metadata = $metadata;
            $source->save();
        } catch (Throwable $exception) {
            // La persistance du brouillon ne doit jamais empecher
            // l'activation : on trace et on continue.
            report($exception);
        }
    }
}
