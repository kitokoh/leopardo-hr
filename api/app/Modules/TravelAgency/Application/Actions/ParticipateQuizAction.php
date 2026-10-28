<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-904 (#6107) — Participation à un quiz (jeu-concours).
 *
 * Réponses notées SERVEUR (la bonne réponse n'est jamais envoyée au
 * participant) ; score = Σ points des bonnes réponses ; bonus si sans
 * faute. Participation bornée : une seule participation par (quiz, email)
 * — contrainte unique en base, rejet 409 au doublon.
 *
 * @return array{participation: TravelQuizParticipation, score: int, bonus: int}
 */
final class ParticipateQuizAction
{
    /**
     * @param  array<int, array<string, mixed>>  $answers
     * @return array{participation: TravelQuizParticipation, score: int, bonus: int}
     */
    public function execute(
        TravelQuiz $quiz,
        ?string $participantEmail,
        ?string $participantName,
        array $answers,
    ): array {
        if ($quiz->status !== QuizStatus::PUBLISHED) {
            abort(422, 'Ce quiz n\'est pas ouvert à la participation.');
        }

        $email = $participantEmail === null ? null : strtolower(trim($participantEmail));

        if ($email === null || $email === '') {
            abort(422, 'Un email de participation est requis.');
        }

        $questions = $quiz->questions()->get();

        // Bornes du contrat : une reponse partielle est licite (le participant
        // peut passer une question, elle compte alors 0), mais elle doit viser
        // une question DE CE quiz et une option REELLEMENT proposee. Sans ce
        // garde-fou, un `question_id` etranger ou un index hors bornes etait
        // ignore silencieusement et produisait un score trompeur.
        foreach ($answers as $answer) {
            $question = $questions->firstWhere('id', (int) ($answer['question_id'] ?? 0));

            if ($question === null) {
                abort(422, 'Une reponse vise une question qui n\'appartient pas a ce quiz.');
            }

            // Le cast Eloquent `array` peut valoir `null` sur une ligne
            // historique anterieure a l'introduction de la colonne : on lit
            // l'attribut BRUT (`mixed`) plutot que la propriete typee, sinon
            // `count(null)` leverait un TypeError en production. PHPStan
            // signalait le `is_array()` comme toujours vrai parce qu'il se fie
            // au docblock `@property array<int, string>` (refs #8396).
            $rawOptions = $question->getAttribute('options');
            $options = is_array($rawOptions) ? $rawOptions : [];

            if ($options !== [] && (int) ($answer['selected_option'] ?? -1) >= count($options)) {
                abort(422, 'Une reponse sort des options proposees.');
            }
        }

        $score = 0;
        $totalPoints = 0;

        foreach ($questions as $question) {
            $totalPoints += (int) $question->points;
            $selected = null;

            foreach ($answers as $answer) {
                if ((int) ($answer['question_id'] ?? 0) === (int) $question->id) {
                    $selected = $answer['selected_option'] ?? null;
                    break;
                }
            }

            if ($selected !== null && (int) $selected === (int) $question->correct_option_index) {
                $score += (int) $question->points;
            }
        }

        $perfect = $totalPoints > 0 && $score === $totalPoints;
        $bonus = $perfect ? $totalPoints : 0;

        try {
            $participation = DB::transaction(fn (): TravelQuizParticipation => TravelQuizParticipation::query()->create([
                'company_id' => $quiz->company_id,
                'quiz_id' => $quiz->id,
                'participant_contact_id' => null,
                'participant_email' => $email,
                // L'unicite est portee par la contrainte
                // (company_id, quiz_id, participant_identifier). La laisser a
                // NULL desactivait la garde : en Postgres `NULL <> NULL`, donc
                // chaque tentative creait une ligne au lieu de lever 409.
                'participant_identifier' => $email,
                'participant_name' => $participantName,
                'answers' => $answers,
                'score' => $score,
                'bonus' => $bonus,
                'status' => 'submitted',
            ]));
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Participation déjà enregistrée pour ce quiz.');
        }

        return ['participation' => $participation, 'score' => $score, 'bonus' => $bonus];
    }
}
