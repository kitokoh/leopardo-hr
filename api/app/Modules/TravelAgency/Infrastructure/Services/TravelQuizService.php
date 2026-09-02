<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours.
 *
 * - Score calculé SERVEUR (comparaison de hash des réponses, jamais de
 *   bonne réponse en clair au repos) ;
 * - participation UNIQUE par (quiz, contact) — contrainte unique en base +
 *   pré-vérification (pattern #4978, pas de catch de contrainte dans la
 *   transaction) ;
 * - résultats cohérents (score ≤ total, borné par les points des questions).
 */
final class TravelQuizService
{
    /**
     * Soumet une participation. Retourne la participation créée.
     *
     * @param  array<string, string>  $answers  question_id => réponse choisie
     */
    public function participate(
        TravelQuiz $quiz,
        string $participantIdentifier,
        array $answers,
    ): TravelQuizParticipation {
        if ($quiz->status !== 'published') {
            abort(422, 'Ce quiz n\'est pas ouvert à la participation.');
        }

        $participantIdentifier = strtolower(trim($participantIdentifier));

        $existing = TravelQuizParticipation::query()
            ->where('company_id', $quiz->company_id)
            ->where('quiz_id', $quiz->id)
            ->where('participant_identifier', $participantIdentifier)
            ->exists();

        if ($existing) {
            abort(422, 'Participation déjà enregistrée pour ce contact.');
        }

        /** @var list<TravelQuizQuestion> $questions */
        $questions = $quiz->questions()->orderBy('rank')->get()->all();

        $score = 0;
        $total = 0;
        $results = [];

        foreach ($questions as $question) {
            $total += (int) $question->points;
            $questionKey = (string) $question->id;
            $chosen = array_key_exists($questionKey, $answers)
                ? (string) $answers[$questionKey]
                : '';
            $correct = hash('sha256', $chosen) === (string) $question->correct_answer_hash;
            $score += $correct ? (int) $question->points : 0;
            $results[(string) $question->id] = $correct;
        }

        return DB::transaction(function () use ($quiz, $participantIdentifier, $answers, $score, $total): TravelQuizParticipation {
            /** @var TravelQuizParticipation $participation */
            $participation = TravelQuizParticipation::query()->create([
                'company_id' => $quiz->company_id,
                'quiz_id' => $quiz->id,
                'participant_identifier' => $participantIdentifier,
                'answers_redacted' => $answers,
                'score' => $score,
                'total_points' => $total,
                'completed_at' => now(),
            ]);

            return $participation;
        });
    }
}
