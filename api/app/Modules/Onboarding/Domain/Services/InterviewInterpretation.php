<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Services;

/**
 * #8229 (BOS-041) — resultat d'une interpretation de description libre.
 *
 * Trois issues possibles, et **aucune quatrieme** : un service qui rendrait
 * « les reponses plus ou moins remplies » obligerait chaque appelant a
 * redecider lui-meme si le resultat est exploitable — et le premier a
 * oublier de le faire activerait un plan invente.
 *
 * - {@see self::interpreted()}  : toutes les valeurs validees, plan possible ;
 * - {@see self::clarification()} : au moins une valeur hors allowlist ou
 *   ambigue ; on demande, on ne devine pas ;
 * - {@see self::unavailable()}   : LLM absent/injoignable/JSON invalide apres
 *   retry ; l'appelant bascule sur l'interview deterministe.
 */
final readonly class InterviewInterpretation
{
    public const OUTCOME_INTERPRETED = 'interpreted';

    public const OUTCOME_CLARIFICATION = 'clarification';

    public const OUTCOME_UNAVAILABLE = 'unavailable';

    /**
     * @param  array<string, mixed>  $answers  Reponses validees (allowlist).
     * @param  list<string>  $clarificationQuestions  Questions a reposer, dans l'ordre.
     * @param  list<string>  $rejected  Valeurs hors allowlist, pour l'audit (jamais pour l'affichage).
     * @param  string|null  $reasonCode  Code machine stable d'indisponibilite.
     */
    private function __construct(
        public string $outcome,
        public array $answers = [],
        public array $clarificationQuestions = [],
        public array $rejected = [],
        public ?string $reasonCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $rejected  Valeurs/cles ecartes, conserves pour l'audit.
     */
    public static function interpreted(array $answers, array $rejected = []): self
    {
        return new self(self::OUTCOME_INTERPRETED, $answers, [], $rejected);
    }

    /**
     * @param  list<string>  $questions
     * @param  list<string>  $rejected
     */
    public static function clarification(array $questions, array $rejected = []): self
    {
        return new self(self::OUTCOME_CLARIFICATION, [], $questions, $rejected);
    }

    public static function unavailable(string $reasonCode): self
    {
        return new self(self::OUTCOME_UNAVAILABLE, [], [], [], $reasonCode);
    }

    public function isInterpreted(): bool
    {
        return $this->outcome === self::OUTCOME_INTERPRETED;
    }

    public function needsClarification(): bool
    {
        return $this->outcome === self::OUTCOME_CLARIFICATION;
    }

    public function isUnavailable(): bool
    {
        return $this->outcome === self::OUTCOME_UNAVAILABLE;
    }
}
