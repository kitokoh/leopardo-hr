<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Services;

use App\Core\AI\Domain\Contracts\ModelInferencePort;
use App\Core\AI\Domain\Enums\ModelType;
use App\Core\AI\Domain\ValueObjects\ModelRequest;
use App\Core\AI\Domain\ValueObjects\ModelVersion;
use App\Modules\Onboarding\Domain\Exceptions\LeaInterpretationUnavailableException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #8229 (BOS-041) — traduction d'une description libre d'activite en
 * reponses d'interview **validees**.
 *
 * C'est la seule brique IA nouvelle du parcours Lea, et son perimetre est
 * volontairement etroit : elle ne produit que des cles/valeurs de
 * l'allowlist {@see SetupInterviewPlanner::QUESTIONS}. Elle n'active rien,
 * ne planifie rien, ne cree rien — l'execution reste le chemin existant
 * (`CompleteSetupInterview`), inchange.
 *
 * ## Pourquoi l'allowlist est appliquee APRES le LLM et non dans le prompt
 *
 * Mettre l'allowlist dans le prompt est une **demande**, pas une garantie :
 * un modele peut rendre `sector: "hotellerie"`, un synonyme plausible mais
 * absent du catalogue de solutions. Le traduire « au plus proche » vers
 * `restaurant` activerait une verticale que le client n'a pas demandee.
 * Toute valeur hors allowlist produit donc une **clarification** : on
 * repose la question, on ne corrige jamais en silence.
 *
 * ## Trois refus explicites
 *
 * 1. **Injection** — la description est une donnee, jamais une consigne.
 *    Une tentative de detournement est rejetee **avant** l'appel : envoyer
 *    le texte a un modele en esperant qu'il resiste est un pari, et le
 *    contenu de la reponse serait de toute facon non validable.
 * 2. **Hors allowlist** — clarification (cf. ci-dessus).
 * 3. **JSON invalide** — exactement **un** retry, puis indisponibilite
 *    propre. Pas de troisieme tentative : chaque appel coute, et un modele
 *    qui echoue deux fois sur le meme prompt n'a pas de raison de reussir
 *    la troisieme.
 *
 * ## Ce qui n'est jamais journalise
 *
 * La description brute peut contenir des noms de personnes ou de clients.
 * Les logs ne portent que l'issue, l'identifiant de correlation et le nombre
 * de valeurs rejetees — jamais le texte, jamais les valeurs.
 */
final class InterviewAnswerExtractor
{
    /** Longueur maximale acceptee pour la description (garde cout + abus). */
    public const MAX_DESCRIPTION_LENGTH = 2000;

    /** Longueur minimale : en dessous, il n'y a rien a interpreter. */
    public const MIN_DESCRIPTION_LENGTH = 12;

    /**
     * Marqueurs d'injection. La liste vise les **formes** de detournement, pas
     * un vocabulaire metier : « ignore les instructions », « tu es
     * desormais », balises de role, tentative d'acceder au prompt.
     *
     * @var list<string>
     */
    private const INJECTION_PATTERNS = [
        'ignore les instructions',
        'ignore all previous',
        'ignore previous instructions',
        'ignore the above',
        'disregard the above',
        'disregard previous',
        'tu es desormais',
        'you are now',
        'system prompt',
        'prompt systeme',
        'prompt système',
        'revele tes instructions',
        'reveal your instructions',
        'affiche tes instructions',
        'nouvelles instructions',
        'new instructions',
        'jailbreak',
        'act as',
        'fais comme si',
        '<|system|>',
        '<|im_start|>',
        '### instruction',
    ];

    public function __construct(
        private readonly ModelInferencePort $inference,
    ) {}

    /**
     * Interprete une description libre.
     *
     * @param  string  $description  Texte libre saisi par l'utilisateur.
     * @param  string  $correlationId  Identifiant de parcours, pour l'audit.
     *
     * @throws LeaInterpretationUnavailableException si la description est vide,
     *                                               trop longue ou contient une tentative d'injection.
     */
    public function extract(string $description, string $correlationId): InterviewInterpretation
    {
        $description = trim($description);

        if (mb_strlen($description) < self::MIN_DESCRIPTION_LENGTH) {
            throw new LeaInterpretationUnavailableException('description_too_short');
        }

        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new LeaInterpretationUnavailableException('description_too_long');
        }

        if ($this->containsInjection($description)) {
            // Rejet AVANT l'appel : le texte hostile n'atteint jamais le
            // modele, donc aucune valeur ne peut en sortir.
            Log::warning('lea.interpret.injection_rejected', [
                'correlation_id' => $correlationId,
            ]);

            throw new LeaInterpretationUnavailableException('injection_detected');
        }

        $raw = $this->askModel($description, $correlationId);

        if ($raw === null) {
            return InterviewInterpretation::unavailable('llm_unavailable');
        }

        return $this->validateAgainstAllowlist($raw, $correlationId);
    }

    /**
     * Appelle le modele, avec **un seul** retry sur reponse inexploitable.
     *
     * @return array<array-key, mixed>|null `null` = indisponible apres retry.
     */
    private function askModel(string $description, string $correlationId): ?array
    {
        $attempts = 0;

        while ($attempts < 2) {
            $attempts++;

            try {
                $result = $this->inference->infer(new ModelRequest(
                    type: ModelType::SetupInterviewExtraction,
                    correlationId: $correlationId,
                    input: [
                        'description' => $description,
                        'questions' => $this->questionSchema(),
                    ],
                    timeoutMs: 20_000,
                ));
            } catch (Throwable $exception) {
                // Un adaptateur qui leve (panne reseau, quota) est traite
                // comme une indisponibilite : le parcours ne doit jamais
                // remonter une exception fournisseur a l'utilisateur.
                report($exception);
                Log::warning('lea.interpret.provider_error', [
                    'correlation_id' => $correlationId,
                    'attempt' => $attempts,
                ]);

                continue;
            }

            if (! $result->isUsable()) {
                Log::info('lea.interpret.unusable_result', [
                    'correlation_id' => $correlationId,
                    'status' => $result->status->value,
                    'attempt' => $attempts,
                ]);

                continue;
            }

            $payload = $result->payload;
            $answers = $payload['answers'] ?? null;

            if (! is_array($answers)) {
                Log::info('lea.interpret.malformed_payload', [
                    'correlation_id' => $correlationId,
                    'attempt' => $attempts,
                ]);

                continue;
            }

            /** @var array<array-key, mixed> $answers */
            return $answers;
        }

        return null;
    }

    /**
     * Filtre les valeurs produites contre l'allowlist du planificateur.
     *
     * Une seule valeur hors allowlist suffit a declencher une clarification :
     * on ne rend pas un plan partiel, qui activerait un sous-ensemble
     * arbitraire de solutions selon ce que le modele a su deviner.
     *
     * Le type est `array<array-key, mixed>` et non `array<string, mixed>` :
     * ces donnees viennent d'un modele, et PHP convertit une cle numerique
     * JSON (`{"0": ...}`) en entier. L'annoncer comme string ferait passer
     * le garde `is_string()` pour une verification inutile alors qu'il
     * protege un cas reel.
     *
     * @param  array<array-key, mixed>  $raw
     */
    private function validateAgainstAllowlist(array $raw, string $correlationId): InterviewInterpretation
    {
        $answers = [];
        $unknownValues = [];
        $clarification = [];

        foreach ($raw as $question => $value) {
            if (! is_string($question) || ! array_key_exists($question, SetupInterviewPlanner::QUESTIONS)) {
                // Cle inconnue : ignoree silencieusement cote utilisateur, mais
                // comptee pour l'audit.
                $unknownValues[] = is_string($question) ? $question : '(non-string)';

                continue;
            }

            // `null` = question sautee, valeur legitime (comme l'interview).
            if ($value === null) {
                $answers[$question] = null;

                continue;
            }

            $allowed = SetupInterviewPlanner::QUESTIONS[$question];

            // `company_name` est en TEXTE LIBRE borne : ce n'est pas une
            // valeur d'activation (plan() ne la lit jamais), donc on
            // l'accepte telle quelle dans les bornes du planner.
            if ($allowed === []) {
                if (is_string($value) && $this->isBoundedFreeText($value)) {
                    $answers[$question] = trim($value);
                } else {
                    $unknownValues[] = $question;
                    $clarification[] = $question;
                }

                continue;
            }

            if (in_array($question, SetupInterviewPlanner::MULTI_CHOICE, true)) {
                $selected = $this->validMultiChoice($allowed, $value);

                if ($selected === null) {
                    // Aucune valeur exploitable : on repose la question
                    // plutot que de rendre un tableau vide, qui signifierait
                    // « aucune priorite » et n'activerait aucun outil.
                    $unknownValues[] = $question;
                    $clarification[] = $question;

                    continue;
                }

                $answers[$question] = $selected;

                continue;
            }

            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                // Valeur hors allowlist : JAMAIS corrigee au plus proche.
                $unknownValues[] = $question;
                $clarification[] = $question;

                continue;
            }

            $answers[$question] = $value;
        }

        if ($clarification !== []) {
            Log::info('lea.interpret.clarification_required', [
                'correlation_id' => $correlationId,
                'rejected_count' => count($unknownValues),
            ]);

            // Ordre stable : suit l'ordre des questions du planner, pour que
            // l'UI pose les questions dans l'ordre de l'interview.
            $ordered = array_values(array_filter(
                array_keys(SetupInterviewPlanner::QUESTIONS),
                fn (string $q): bool => in_array($q, $clarification, true),
            ));

            return InterviewInterpretation::clarification($ordered, $unknownValues);
        }

        if ($answers === []) {
            return InterviewInterpretation::unavailable('no_answers_produced');
        }

        // Les cles/valeurs ecartees sont conservees MEME sur le chemin
        // nominal : sans elles, l'audit ne verrait pas qu'un modele a tente
        // de produire un champ hors allowlist (ex. `is_admin`) qui a ete
        // ignore. Une reponse valide peut masquer une tentative.
        return InterviewInterpretation::interpreted($answers, $unknownValues);
    }

    /**
     * Valide un choix multiple : seules les valeurs de l'allowlist sont
     * retenues. `null` = aucune valeur exploitable.
     *
     * @param  list<string>  $allowed
     * @return list<string>|null
     */
    private function validMultiChoice(array $allowed, mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $selected = [];
        foreach ($value as $item) {
            if (is_string($item) && in_array($item, $allowed, true) && ! in_array($item, $selected, true)) {
                $selected[] = $item;
            }
        }

        return $selected === [] ? null : $selected;
    }

    private function isBoundedFreeText(string $value): bool
    {
        $length = mb_strlen(trim($value));

        return $length >= 2 && $length <= SetupInterviewPlanner::FREE_TEXT_MAX;
    }

    private function containsInjection(string $description): bool
    {
        $haystack = mb_strtolower($description);

        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Schema transmis au modele : les cles et les valeurs autorisees, pour
     * qu'il ait la meilleure chance de rester dedans. Ce n'est qu'une aide —
     * la validation reelle a lieu dans validateAgainstAllowlist().
     *
     * @return array<string, list<string>>
     */
    private function questionSchema(): array
    {
        $schema = [];

        foreach (SetupInterviewPlanner::QUESTIONS as $question => $allowed) {
            if ($allowed !== []) {
                $schema[$question] = $allowed;
            }
        }

        return $schema;
    }

    /** Version du modele attendue, portee pour l'audit. */
    public static function modelVersion(): ModelVersion
    {
        return new ModelVersion('setup-interview-extractor', 'v1');
    }
}
