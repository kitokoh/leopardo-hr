<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-904 (#6107) — Participation à un quiz (jeu-concours).
 *
 * Le score est TOUJOURS calculé côté serveur (comparaison des réponses aux
 * `correct_option_index` — jamais exposés au client) ; la participation est
 * UNIQUE par (tenant, quiz, participant) — contrainte DB + garde applicative
 * → participation bornée. Le quiz doit être publié.
 */
final class ParticipateInTravelQuizAction
{
    public function execute(TravelQuiz $quiz, Employee $actor, array $answers): TravelQuizParticipation
    {
        if ($quiz->status !== TravelQuiz::STATUS_PUBLISHED) {
            abort(422, 'Ce quiz nest pas publie.');
        }

        $questions = TravelQuizQuestion::query()
            ->where('company_id', $quiz->company_id)
            ->where('quiz_id', $quiz->id)
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            abort(422, 'Ce quiz ne contient aucune question.');
        }

        if (count($answers) !== $questions->count()) {
            abort(422, 'Le nombre de reponses ne correspond pas aux questions.');
        }

        foreach ($answers as $index) {
            if (! is_int($index) || $index < 0) {
                abort(422, 'Reponses invalides.');
            }
        }

        $existing = TravelQuizParticipation::query()
            ->where('company_id', $quiz->company_id)
            ->where('quiz_id', $quiz->id)
            ->where('participant_type', 'employee')
            ->where('participant_id', $actor->id)
            ->exists();

        if ($existing) {
            abort(422, 'Participation deja enregistree pour cet employe.');
        }

        $score = 0;
        foreach ($questions as $position => $question) {
            $chosen = $answers[$position] ?? -1;
            $maxIndex = count((array) $question->options) - 1;

            if ($chosen < 0 || $chosen > $maxIndex) {
                abort(422, 'Reponse hors bornes pour une question.');
            }

            if ($chosen === (int) $question->correct_option_index) {
                $score += (int) $question->points;
            }
        }

        return DB::transaction(fn (): TravelQuizParticipation => TravelQuizParticipation::query()->create([
            'company_id' => $quiz->company_id,
            'quiz_id' => $quiz->id,
            'participant_type' => 'employee',
            'participant_id' => $actor->id,
            'answers' => $answers,
            'score' => $score,
            'status' => 'completed',
            'completed_at' => now(),
        ]));
    }
}
