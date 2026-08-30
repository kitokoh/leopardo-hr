<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
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
    public function execute(
        TravelQuiz $quiz,
        ?string $participantEmail,
        ?string $participantName,
        array $answers,
    ): array {
        if ($quiz->status !== QuizStatus::ACTIVE) {
            abort(422, 'Ce quiz n\'est pas ouvert à la participation.');
        }

        $email = $participantEmail === null ? null : strtolower(trim($participantEmail));

        if ($email === null || $email === '') {
            abort(422, 'Un email de participation est requis.');
        }

        $questions = $quiz->questions()->get();
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
